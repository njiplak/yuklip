<?php

namespace App\Http\Controllers\WhatsApp;

use App\Ai\Agents\GuestReplyAgent;
use App\Ai\Agents\PreferenceExtractorAgent;
use App\Ai\Agents\StaffBriefingAgent;
use App\Ai\Agents\UpsellReplyAgent;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\UpsellLog;
use App\Models\WebhookLog;
use App\Models\WhatsappMessage;
use App\Service\WhatsApp\TwoChatService;
use App\Utils\WebResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WebhookController extends Controller
{
    public function handle(Request $request, TwoChatService $twoChat): JsonResponse
    {
        $webhookLog = WebhookLog::create([
            'source' => 'whatsapp',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => collect($request->headers->all())->except(['cookie'])->toArray(),
            'payload' => $request->all(),
            'ip_address' => $request->ip(),
        ]);

        if ($request->header('User-Agent') !== '2Chat') {
            Log::warning('WhatsApp webhook rejected — unexpected User-Agent', [
                'user_agent' => $request->header('User-Agent'),
                'ip' => $request->ip(),
            ]);

            $webhookLog->update([
                'status_code' => 403,
                'response_body' => ['error' => 'Unauthorized'],
            ]);

            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->has('event')) {
            Log::info('WhatsApp system event', ['event' => $request->input('event')]);
            return WebResponse::json(['event' => $request->input('event')], 'Event logged');
        }

        if ($request->has('reaction')) {
            Log::info('WhatsApp reaction received', ['reaction' => $request->input('reaction')]);
            return WebResponse::json(['reaction' => $request->input('reaction')], 'Reaction logged');
        }

        $phone = $request->input('remote_phone_number');
        $sentBy = $request->input('sent_by');

        if ($sentBy !== 'user' || !$phone) {
            return WebResponse::json(null, 'Skipped');
        }

        $text = $this->extractMessageText($request);

        if (!$text) {
            return WebResponse::json(null, 'Skipped');
        }

        $booking = Booking::where('guest_phone', $phone)
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->latest('check_in')
            ->first();

        // Idempotent: if 2Chat retries with same UUID, don't duplicate
        $messageUuid = $request->input('uuid');
        if ($messageUuid && WhatsappMessage::where('twochat_message_id', $messageUuid)->exists()) {
            return WebResponse::json(null, 'Already processed');
        }

        WhatsappMessage::create([
            'booking_id' => $booking?->id,
            'direction' => 'inbound',
            'phone_number' => $phone,
            'message_body' => $text,
            'twochat_message_id' => $messageUuid,
            'received_at' => now(),
        ]);

        // Rate limit replies to prevent abuse (20 per minute per phone)
        $rateLimitKey = 'whatsapp:reply:' . $phone;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
            return WebResponse::json(null, 'Rate limited');
        }
        RateLimiter::hit($rateLimitKey, 60);

        if (!$booking) {
            return $this->sendFallbackReply($phone, $twoChat);
        }

        // Bot stays silent when guest is in manager handover or cancelled state
        if (in_array($booking->conversation_state, ['handover_human', 'cancelled'])) {
            SystemLog::create([
                'agent' => 'guest_reply',
                'action' => 'skipped',
                'booking_id' => $booking->id,
                'status' => 'skipped',
                'payload' => ['reason' => 'state_' . $booking->conversation_state],
            ]);
            return WebResponse::json(null, 'Skipped — ' . $booking->conversation_state);
        }

        // Non-text messages (images, voice notes, videos) — politely ask for text
        if (str_starts_with($text, '[Sent a ')) {
            return $this->handleNonTextMessage($booking, $text, $twoChat);
        }

        // Reset follow-up count on any guest reply (they're engaging)
        if ($booking->follow_up_count > 0) {
            $booking->update(['follow_up_count' => 0]);
        }

        if ($this->isPendingUpsellReply($booking)) {
            return $this->handleUpsellReply($booking, $text, $twoChat);
        }

        return $this->handleGuestReply($booking, $text, $twoChat);
    }

    protected function handleGuestReply(Booking $booking, string $text, TwoChatService $twoChat): JsonResponse
    {
        $start = microtime(true);

        try {
            // Extract preferences if still collecting
            if ($booking->conversation_state !== 'preferences_complete') {
                $this->extractPreferences($booking, $text);
            }

            $response = (new GuestReplyAgent($booking))->prompt($text);
            $reply = (string) $response;

            $result = $twoChat->sendMessage($booking->guest_phone, $reply);

            WhatsappMessage::create([
                'booking_id' => $booking->id,
                'direction' => 'outbound',
                'phone_number' => $booking->guest_phone,
                'message_body' => $reply,
                'agent_source' => 'guest_reply',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'guest_reply',
                'action' => 'reply_sent',
                'booking_id' => $booking->id,
                'status' => 'success',
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            // Send staff briefing once when preferences first complete
            if ($booking->conversation_state === 'preferences_complete' && !$booking->preferences_briefing_sent) {
                $booking->update(['preferences_briefing_sent' => true]);
                $this->sendPreferencesBriefing($booking, $twoChat);
            }

            return WebResponse::json(null, 'Reply sent');
        } catch (\Throwable $e) {
            Log::error('Guest reply failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);

            SystemLog::create([
                'agent' => 'guest_reply',
                'action' => 'reply_sent',
                'booking_id' => $booking->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            return WebResponse::json(null, 'Accepted', 200);
        }
    }

    protected function extractPreferences(Booking $booking, string $text): void
    {
        try {
            $result = (new PreferenceExtractorAgent($booking))->prompt($text);
            $extracted = $result->toArray();

            $updates = [];

            if (!empty($extracted['arrival_time']) && !$booking->pref_arrival_time) {
                $updates['pref_arrival_time'] = $extracted['arrival_time'];
            }

            if (!empty($extracted['bed_type']) && !$booking->pref_bed_type) {
                $updates['pref_bed_type'] = $extracted['bed_type'];
            }

            if (!empty($extracted['airport_transfer']) && !$booking->pref_airport_transfer) {
                $updates['pref_airport_transfer'] = $extracted['airport_transfer'];
            }

            if (!empty($extracted['special_requests']) && !$booking->pref_special_requests) {
                $updates['pref_special_requests'] = $extracted['special_requests'];
            }

            if (!empty($updates)) {
                $booking->update($updates);
                $booking->refresh();

                SystemLog::create([
                    'agent' => 'preference_extractor',
                    'action' => 'preferences_extracted',
                    'booking_id' => $booking->id,
                    'status' => 'success',
                    'payload' => $updates,
                ]);
            }

            // Update conversation state based on what's collected
            $allCollected = $booking->pref_arrival_time
                && $booking->pref_bed_type
                && $booking->pref_airport_transfer
                && $booking->pref_special_requests;

            $anyCollected = $booking->pref_arrival_time
                || $booking->pref_bed_type
                || $booking->pref_airport_transfer
                || $booking->pref_special_requests;

            $newState = $allCollected
                ? 'preferences_complete'
                : ($anyCollected ? 'preferences_partial' : 'waiting_preferences');

            if ($newState !== $booking->conversation_state) {
                $booking->update(['conversation_state' => $newState]);
                $booking->refresh();

                SystemLog::create([
                    'agent' => 'preference_extractor',
                    'action' => 'state_changed',
                    'booking_id' => $booking->id,
                    'status' => 'success',
                    'payload' => ['from' => $booking->getOriginal('conversation_state'), 'to' => $newState],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Preference extraction failed, continuing with reply', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendPreferencesBriefing(Booking $booking, TwoChatService $twoChat): void
    {
        $staffNumber = config('whatsapp.staff_phone_number');

        if (!$staffNumber) {
            return;
        }

        try {
            $arrivals = [[
                'guest_name' => $booking->guest_name,
                'suite_name' => $booking->suite_name,
                'num_guests' => $booking->num_guests,
                'guest_nationality' => $booking->guest_nationality,
                'special_requests' => implode(' | ', array_filter([
                    "Arrival: {$booking->pref_arrival_time}",
                    "Bed: {$booking->pref_bed_type}",
                    "Transfer: {$booking->pref_airport_transfer}",
                    $booking->pref_special_requests !== 'none' ? $booking->pref_special_requests : null,
                ])),
            ]];

            $response = (new StaffBriefingAgent($arrivals, []))->prompt(
                'Generate a guest preparation briefing. All preferences have been collected from this guest via WhatsApp. Include all preference details so staff can prepare the suite and any transfers.'
            );

            $briefing = (string) $response;
            $result = $twoChat->sendMessage($staffNumber, $briefing);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffNumber,
                'message_body' => $briefing,
                'agent_source' => 'preference_briefing',
                'booking_id' => $booking->id,
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'preference_extractor',
                'action' => 'briefing_sent',
                'booking_id' => $booking->id,
                'status' => 'success',
            ]);
        } catch (\Throwable $e) {
            Log::error('Preferences briefing failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleUpsellReply(Booking $booking, string $guestReply, TwoChatService $twoChat): JsonResponse
    {
        $start = microtime(true);
        $offer = $booking->currentOffer;

        if (!$offer) {
            $booking->update([
                'current_upsell_offer_id' => null,
                'upsell_offer_sent_at' => null,
            ]);

            return $this->handleGuestReply($booking, $guestReply, $twoChat);
        }

        try {
            $response = (new UpsellReplyAgent($booking, $offer))->prompt($guestReply);

            if (!isset($response['classification'], $response['reply_message'])) {
                throw new \RuntimeException('UpsellReplyAgent returned invalid structured output');
            }

            $classification = $response['classification'];
            $replyMessage = $response['reply_message'];

            $upsellLog = UpsellLog::where('booking_id', $booking->id)
                ->where('offer_id', $offer->id)
                ->whereNull('guest_reply')
                ->latest()
                ->first();

            if ($upsellLog) {
                $upsellLog->update([
                    'guest_reply' => $guestReply,
                    'reply_received_at' => now(),
                    'outcome' => $classification,
                    'revenue_generated' => $classification === 'accepted' ? $offer->price : null,
                ]);
            }

            if ($classification === 'accepted') {
                if ($offer->price) {
                    Transaction::create([
                        'booking_id' => $booking->id,
                        'type' => 'income',
                        'category' => 'upsell',
                        'description' => "Upsell: {$offer->title}",
                        'amount' => $offer->price,
                        'currency' => $offer->currency,
                        'transaction_date' => now()->toDateString(),
                        'recorded_by' => 'system',
                    ]);
                }

                $this->notifyManagerUpsellAccepted($booking, $offer, $twoChat);
            }

            if (in_array($classification, ['accepted', 'declined'])) {
                $booking->update([
                    'current_upsell_offer_id' => null,
                    'upsell_offer_sent_at' => null,
                ]);
            }

            $result = $twoChat->sendMessage($booking->guest_phone, $replyMessage);

            WhatsappMessage::create([
                'booking_id' => $booking->id,
                'direction' => 'outbound',
                'phone_number' => $booking->guest_phone,
                'message_body' => $replyMessage,
                'agent_source' => 'upsell_recv',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'upsell_recv',
                'action' => 'reply_received',
                'booking_id' => $booking->id,
                'payload' => ['classification' => $classification, 'offer_code' => $offer->offer_code],
                'status' => 'success',
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            return WebResponse::json(null, 'Upsell reply processed');
        } catch (\Throwable $e) {
            Log::error('Upsell reply failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);

            SystemLog::create([
                'agent' => 'upsell_recv',
                'action' => 'reply_received',
                'booking_id' => $booking->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            return WebResponse::json(null, 'Accepted', 200);
        }
    }

    protected function sendFallbackReply(string $phone, TwoChatService $twoChat): JsonResponse
    {
        // Don't repeat fallback to same phone within an hour
        $recentlySent = WhatsappMessage::where('phone_number', $phone)
            ->where('direction', 'outbound')
            ->whereNull('booking_id')
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if ($recentlySent) {
            SystemLog::create([
                'agent' => 'guest_reply',
                'action' => 'skipped',
                'status' => 'skipped',
                'payload' => ['phone' => $phone, 'reason' => 'fallback_rate_limited'],
            ]);

            return WebResponse::json(null, 'Fallback already sent');
        }

        $fallback = "Thank you for your message. We don't have an active booking for this number. " .
            "Please contact us at the riad directly.\n\n" .
            "Merci pour votre message. Nous n'avons pas de réservation active pour ce numéro. " .
            "Veuillez nous contacter directement au riad.";

        try {
            $result = $twoChat->sendMessage($phone, $fallback);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $phone,
                'message_body' => $fallback,
                'agent_source' => 'guest_reply',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallback reply failed', ['phone' => $phone, 'error' => $e->getMessage()]);
        }

        SystemLog::create([
            'agent' => 'guest_reply',
            'action' => 'skipped',
            'status' => 'skipped',
            'payload' => ['phone' => $phone, 'reason' => 'no_active_booking'],
        ]);

        return WebResponse::json(null, 'Fallback sent');
    }

    protected function handleNonTextMessage(Booking $booking, string $mediaPlaceholder, TwoChatService $twoChat): JsonResponse
    {
        $reply = "I can only read text messages for now. Could you type out your message instead? I'm happy to help with anything you need!";

        try {
            $result = $twoChat->sendMessage($booking->guest_phone, $reply);

            WhatsappMessage::create([
                'booking_id' => $booking->id,
                'direction' => 'outbound',
                'phone_number' => $booking->guest_phone,
                'message_body' => $reply,
                'agent_source' => 'guest_reply',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Non-text reply failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
        }

        SystemLog::create([
            'agent' => 'guest_reply',
            'action' => 'non_text_received',
            'booking_id' => $booking->id,
            'status' => 'success',
            'payload' => ['media' => $mediaPlaceholder],
        ]);

        return WebResponse::json(null, 'Non-text handled');
    }

    protected function notifyManagerUpsellAccepted(Booking $booking, \App\Models\Offer $offer, TwoChatService $twoChat): void
    {
        $staffNumber = config('whatsapp.staff_phone_number');

        if (!$staffNumber) {
            return;
        }

        $alert = implode("\n", [
            "UPSELL ACCEPTED",
            "",
            "Guest: {$booking->guest_name}",
            "Suite: {$booking->suite_name}",
            "Offer: {$offer->title}",
            "Price: {$offer->price} {$offer->currency}",
            "",
            "Please prepare this service for the guest.",
        ]);

        try {
            $twoChat->sendMessage($staffNumber, $alert);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffNumber,
                'message_body' => $alert,
                'agent_source' => 'upsell_recv',
                'booking_id' => $booking->id,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Manager upsell notification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function isPendingUpsellReply(Booking $booking): bool
    {
        return $booking->current_upsell_offer_id !== null
            && $booking->upsell_offer_sent_at !== null
            && $booking->upsell_offer_sent_at->diffInHours(now()) < 48;
    }

    protected function extractMessageText(Request $request): ?string
    {
        $message = $request->input('message', []);

        if (isset($message['old_text'])) {
            return $message['text'] ?? null;
        }

        if (isset($message['order'])) {
            $products = collect($message['order']['products'] ?? [])
                ->map(fn ($p) => sprintf('%s (x%d) - %s %s', $p['name'] ?? '', $p['quantity'] ?? 0, $p['currency'] ?? '', $p['price'] ?? '0'))
                ->implode("\n");
            return "I'd like to order:\n{$products}";
        }

        if (isset($message['text'])) {
            return $message['text'];
        }

        if (isset($message['media'])) {
            $type = $message['media']['type'] ?? 'file';
            return "[Sent a {$type}]";
        }

        return null;
    }
}
