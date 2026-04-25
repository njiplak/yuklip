<?php

namespace App\Http\Controllers\WhatsApp;

use App\Ai\Agents\CustomerProfileAgent;
use App\Ai\Agents\ExpenseParserAgent;
use App\Ai\Agents\GuestReplyAgent;
use App\Ai\Agents\PreferenceExtractorAgent;
use App\Ai\Agents\ServiceRequestDetectorAgent;
use App\Ai\Agents\StaffBriefingAgent;
use App\Ai\Agents\UpsellReplyAgent;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\UpsellLog;
use App\Models\WebhookLog;
use App\Models\WhatsappMessage;
use App\Service\PushNotificationService;
use App\Service\WhatsApp\NighttimeQueue;
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

        // Manager commands: DONE handler and expense input
        $staffPhone = config('whatsapp.staff_phone_number');
        if ($staffPhone && $phone === $staffPhone) {
            // DONE-{bookingId} to resume automation
            if (preg_match('/^DONE[- ](\d+)$/i', trim($text), $matches)) {
                return $this->handleDoneCommand((int) $matches[1], $twoChat);
            }

            // Expense input (starts with "expense" or "dépense" or looks like an expense)
            if (preg_match('/^(expense|dépense|depense|paid|payé)\b/i', trim($text))) {
                return $this->handleExpenseInput($text, $phone, $twoChat);
            }

            // Cancel last expense within 5-minute window
            if (strtoupper(trim($text)) === 'NO') {
                $pendingCancel = cache()->pull("expense_cancel:{$phone}");
                if ($pendingCancel) {
                    $transaction = Transaction::find($pendingCancel);
                    if ($transaction) {
                        $transaction->delete();
                        try {
                            $twoChat->sendMessage($phone, "Expense cancelled and removed.");
                        } catch (\Throwable) {}

                        SystemLog::create([
                            'agent' => 'expense_input',
                            'action' => 'expense_cancelled',
                            'status' => 'success',
                            'payload' => ['transaction_id' => $pendingCancel],
                        ]);

                        return WebResponse::json(null, 'Expense cancelled');
                    }
                }
            }
        }

        if (!$booking) {
            return $this->sendFallbackReply($phone, $twoChat);
        }

        // Bot stays silent when guest is in paused/escalated states
        if (in_array($booking->conversation_state, Booking::AI_PAUSED_STATES, true)) {
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
                $booking->refresh();

                // If sentiment detection escalated to issue_detected or handover_human, bot stays silent
                if (in_array($booking->conversation_state, ['issue_detected', 'handover_human'])) {
                    SystemLog::create([
                        'agent' => 'guest_reply',
                        'action' => 'skipped',
                        'booking_id' => $booking->id,
                        'status' => 'skipped',
                        'payload' => ['reason' => 'sentiment_escalated_to_' . $booking->conversation_state],
                    ]);
                    return WebResponse::json(null, 'Escalated — bot silent');
                }
            }

            $response = (new GuestReplyAgent($booking))->prompt($text);
            $reply = (string) $response;

            NighttimeQueue::sendOrQueue($twoChat, $booking->guest_phone, $reply, $booking->id, 'guest_reply');

            SystemLog::create([
                'agent' => 'guest_reply',
                'action' => 'reply_sent',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => NighttimeQueue::isNighttime() ? ['queued' => true] : null,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            // Detect service requests and notify staff if action is needed
            $this->detectAndNotifyServiceRequest($booking, $text, $reply, $twoChat);

            // Send staff briefing and update customer profile once when preferences first complete
            if ($booking->conversation_state === 'preferences_complete' && !$booking->preferences_briefing_sent) {
                $booking->update(['preferences_briefing_sent' => true]);
                $this->sendPreferencesBriefing($booking, $twoChat);
                $this->regenerateCustomerProfile($booking);
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

            // Update detected language if available
            if (!empty($extracted['detected_language'])) {
                $updates['detected_language'] = $extracted['detected_language'];
            }

            if (!empty($updates)) {
                $booking->update($updates);
                $booking->refresh();

                // Sync extracted preferences to customer's compounding profile
                $prefUpdates = array_diff_key($updates, ['detected_language' => true]);
                if (!empty($prefUpdates)) {
                    $this->syncPreferencesToCustomer($booking, $prefUpdates);
                }

                SystemLog::create([
                    'agent' => 'preference_extractor',
                    'action' => 'preferences_extracted',
                    'booking_id' => $booking->id,
                    'status' => 'success',
                    'payload' => $updates,
                ]);
            }

            // Handle sentiment: issue_detected or handover_human
            $sentiment = $extracted['sentiment'] ?? 'normal';

            if ($sentiment === 'issue_detected' || $sentiment === 'handover_human') {
                $booking->update(['conversation_state' => $sentiment]);
                $booking->refresh();

                // Alert manager about the issue
                $this->alertManagerEscalation($booking, $sentiment, $text);

                SystemLog::create([
                    'agent' => 'preference_extractor',
                    'action' => 'sentiment_escalated',
                    'booking_id' => $booking->id,
                    'status' => 'success',
                    'payload' => ['sentiment' => $sentiment],
                ]);

                return;
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

    /**
     * Alert manager when guest sentiment triggers escalation.
     * SPEC: issue_detected (angry/frustrated) or handover_human (uninterpretable).
     */
    protected function alertManagerEscalation(Booking $booking, string $sentiment, string $guestMessage): void
    {
        $staffPhone = config('whatsapp.staff_phone_number');

        if (!$staffPhone) {
            return;
        }

        $reason = $sentiment === 'issue_detected' ? 'Guest appears frustrated' : 'Could not interpret message';
        PushNotificationService::escalation($booking->guest_name, $booking->suite_name, $reason);

        $label = $sentiment === 'issue_detected' ? 'ISSUE DETECTED' : 'ESCALATION — UNINTERPRETABLE';
        $instruction = $sentiment === 'issue_detected'
            ? 'Guest appears frustrated or upset. Please contact them directly. Automated messaging is paused.'
            : 'Could not interpret the guest\'s message. Please contact them directly. Automated messaging is paused.';

        $alert = implode("\n", [
            $label,
            "",
            "Guest: {$booking->guest_name}",
            "Suite: {$booking->suite_name}",
            "Phone: {$booking->guest_phone}",
            "",
            "Guest said: \"{$guestMessage}\"",
            "",
            $instruction,
            "",
            "Reply DONE-{$booking->id} when ready to resume automation.",
        ]);

        try {
            $twoChat = app(TwoChatService::class);
            $twoChat->sendMessage($staffPhone, $alert);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffPhone,
                'message_body' => $alert,
                'agent_source' => 'escalation',
                'booking_id' => $booking->id,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Escalation alert failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
        }
    }

    protected function syncPreferencesToCustomer(Booking $booking, array $extractedUpdates): void
    {
        $customer = $booking->customer;

        if (!$customer) {
            return;
        }

        $prefs = $customer->raw_preferences ?? [];

        // Map booking pref columns to customer raw_preferences keys
        $mapping = [
            'pref_bed_type' => 'bed_type',
            'pref_airport_transfer' => 'airport_transfer',
            'pref_special_requests' => 'special_requests',
        ];

        foreach ($mapping as $bookingField => $prefKey) {
            if (isset($extractedUpdates[$bookingField])) {
                $prefs[$prefKey] = $extractedUpdates[$bookingField];
            }
        }

        // Arrival time is per-stay, don't persist to customer profile

        $customer->update(['raw_preferences' => $prefs]);
    }

    protected function regenerateCustomerProfile(Booking $booking): void
    {
        $customer = $booking->customer;

        if (!$customer || empty($customer->raw_preferences)) {
            return;
        }

        try {
            $response = (new CustomerProfileAgent($customer))->prompt(
                'Generate the profile summary for this guest based on their accumulated preferences.'
            );

            $customer->update(['profile_summary' => (string) $response]);

            SystemLog::create([
                'agent' => 'customer_profile',
                'action' => 'profile_regenerated',
                'booking_id' => $booking->id,
                'status' => 'success',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Customer profile regeneration failed', [
                'customer_id' => $customer->id,
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
                'Generate a guest preparation briefing in both French and Arabic. All preferences have been collected from this guest via WhatsApp. Include all preference details so staff can prepare the suite and any transfers.'
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

    protected function detectAndNotifyServiceRequest(
        Booking $booking,
        string $guestMessage,
        string $botReply,
        TwoChatService $twoChat,
    ): void {
        try {
            $result = (new ServiceRequestDetectorAgent($booking))->prompt($guestMessage);
            $detection = $result->toArray();

            if (empty($detection['requires_staff_action'])) {
                return;
            }

            PushNotificationService::serviceRequest(
                $booking->guest_name,
                $booking->suite_name,
                $detection['request_summary'] ?? 'Service request',
            );

            $staffNumber = config('whatsapp.staff_phone_number');

            if (!$staffNumber) {
                return;
            }

            $urgencyLabel = ($detection['urgency'] ?? 'normal') === 'urgent' ? 'URGENT ' : '';

            $alert = implode("\n", [
                "{$urgencyLabel}SERVICE REQUEST",
                '',
                "Guest: {$booking->guest_name}",
                "Suite: {$booking->suite_name}",
                "Request: {$detection['request_summary']}",
                '',
                "Guest said: \"{$guestMessage}\"",
                "Bot replied: \"{$botReply}\"",
                '',
                'Please follow up with the guest.',
            ]);

            $result = $twoChat->sendMessage($staffNumber, $alert);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffNumber,
                'message_body' => $alert,
                'agent_source' => 'service_request',
                'booking_id' => $booking->id,
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'service_request',
                'action' => 'staff_notified',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => [
                    'summary' => $detection['request_summary'],
                    'urgency' => $detection['urgency'] ?? 'normal',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Service request detection failed', [
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
            $details = $response['details'] ?? null;
            $alternativeOfferCode = $response['alternative_offer_code'] ?? null;
            $customRequest = $response['custom_request'] ?? null;

            // Update upsell log with reply
            $upsellLog = UpsellLog::where('booking_id', $booking->id)
                ->where('offer_id', $offer->id)
                ->whereNull('guest_reply')
                ->latest()
                ->first();

            $logOutcome = match ($classification) {
                'accepted', 'accept_and_more' => 'accepted',
                'declined' => 'declined',
                'different_request' => 'custom_request',
                'unrelated' => 'no_response',
                default => 'pending', // question — offer still active
            };

            if ($upsellLog) {
                $upsellLog->update([
                    'guest_reply' => $guestReply,
                    'reply_received_at' => now(),
                    'outcome' => $logOutcome,
                    'revenue_generated' => in_array($classification, ['accepted', 'accept_and_more']) ? $offer->price : null,
                ]);
            }

            // Handle accepted / accept_and_more — log revenue, notify manager
            if (in_array($classification, ['accepted', 'accept_and_more'])) {
                if ($offer->price) {
                    Transaction::create([
                        'booking_id' => $booking->id,
                        'type' => 'income',
                        'category' => 'upsell',
                        'description' => "Upsell: {$offer->title}" . ($details ? " ({$details})" : ''),
                        'amount' => $offer->price,
                        'currency' => $offer->currency,
                        'transaction_date' => now()->toDateString(),
                        'recorded_by' => 'system',
                    ]);
                }

                $this->notifyManagerUpsellAccepted($booking, $offer, $twoChat, $details);
            }

            // Handle accept_and_more — also notify manager about the additional request
            if ($classification === 'accept_and_more' && ($customRequest || $alternativeOfferCode)) {
                $this->notifyManagerCustomRequest($booking, $customRequest, $alternativeOfferCode, $guestReply, $twoChat);
            }

            // Handle different_request — notify manager about custom/alternative request
            if ($classification === 'different_request') {
                $this->notifyManagerCustomRequest($booking, $customRequest, $alternativeOfferCode, $guestReply, $twoChat);
            }

            // Clear active offer for terminal classifications (not question — offer stays active)
            if ($classification !== 'question') {
                $booking->update([
                    'current_upsell_offer_id' => null,
                    'upsell_offer_sent_at' => null,
                ]);
            }

            NighttimeQueue::sendOrQueue($twoChat, $booking->guest_phone, $replyMessage, $booking->id, 'upsell_recv');

            SystemLog::create([
                'agent' => 'upsell_recv',
                'action' => 'reply_received',
                'booking_id' => $booking->id,
                'payload' => array_filter([
                    'classification' => $classification,
                    'offer_code' => $offer->offer_code,
                    'details' => $details,
                    'alternative_offer_code' => $alternativeOfferCode,
                    'custom_request' => $customRequest,
                ]),
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

    protected function notifyManagerUpsellAccepted(Booking $booking, \App\Models\Offer $offer, TwoChatService $twoChat, ?string $details = null): void
    {
        PushNotificationService::upsellAccepted(
            $booking->guest_name,
            $offer->title,
            "{$offer->price} {$offer->currency}",
        );

        $staffNumber = config('whatsapp.staff_phone_number');

        if (!$staffNumber) {
            return;
        }

        $lines = [
            "UPSELL ACCEPTED",
            "",
            "Guest: {$booking->guest_name}",
            "Suite: {$booking->suite_name}",
            "Offer: {$offer->title}",
            "Price: {$offer->price} {$offer->currency}",
        ];

        if ($details) {
            $lines[] = "Details: {$details}";
        }

        $lines[] = "";
        $lines[] = "Please prepare this service for the guest.";

        $alert = implode("\n", $lines);

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

    /**
     * Notify manager about a custom/off-menu request from a guest.
     * SPEC: DIFFERENT_REQUEST and ACCEPT_AND_MORE (additional request) trigger this.
     */
    protected function notifyManagerCustomRequest(
        Booking $booking,
        ?string $customRequest,
        ?string $alternativeOfferCode,
        string $guestReply,
        TwoChatService $twoChat,
    ): void {
        PushNotificationService::customRequest(
            $booking->guest_name,
            $booking->suite_name,
            $customRequest ?? $guestReply,
        );

        $staffNumber = config('whatsapp.staff_phone_number');

        if (!$staffNumber) {
            return;
        }

        $lines = [
            "CUSTOM REQUEST",
            "",
            "Guest: {$booking->guest_name}",
            "Suite: {$booking->suite_name}",
        ];

        if ($alternativeOfferCode) {
            $lines[] = "Matches catalog offer: {$alternativeOfferCode}";
        }

        if ($customRequest) {
            $lines[] = "Request: {$customRequest}";
        }

        $lines[] = "";
        $lines[] = "Guest said: \"{$guestReply}\"";
        $lines[] = "";
        $lines[] = "Please respond directly to the guest.";

        $alert = implode("\n", $lines);

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

            SystemLog::create([
                'agent' => 'upsell_recv',
                'action' => 'custom_request_notified',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => array_filter([
                    'custom_request' => $customRequest,
                    'alternative_offer_code' => $alternativeOfferCode,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Manager custom request notification failed', [
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

    /**
     * DONE handler: manager sends "DONE-{bookingId}" to resume automation.
     * Resets conversation_state from handover/paused states to preferences_partial.
     */
    protected function handleDoneCommand(int $bookingId, TwoChatService $twoChat): JsonResponse
    {
        $staffPhone = config('whatsapp.staff_phone_number');
        $booking = Booking::find($bookingId);

        if (!$booking) {
            if ($staffPhone) {
                try {
                    $twoChat->sendMessage($staffPhone, "Booking #{$bookingId} not found. Please check the ID.");
                } catch (\Throwable $e) {
                    Log::error('DONE error reply failed', ['error' => $e->getMessage()]);
                }
            }
            return WebResponse::json(null, 'Booking not found');
        }

        $pausedStates = ['handover_human', 'issue_detected', 'group_booking', 'suite_pending'];

        if (!in_array($booking->conversation_state, $pausedStates)) {
            if ($staffPhone) {
                try {
                    $twoChat->sendMessage($staffPhone, "Booking #{$bookingId} ({$booking->guest_name}) is in state '{$booking->conversation_state}' — not in a paused state. No action taken.");
                } catch (\Throwable $e) {
                    Log::error('DONE error reply failed', ['error' => $e->getMessage()]);
                }
            }
            return WebResponse::json(null, 'Not in paused state');
        }

        $previousState = $booking->conversation_state;
        $booking->update([
            'conversation_state' => 'waiting_preferences',
            'follow_up_count' => 0,
        ]);

        SystemLog::create([
            'agent' => 'done_handler',
            'action' => 'automation_resumed',
            'booking_id' => $booking->id,
            'status' => 'success',
            'payload' => ['previous_state' => $previousState],
        ]);

        if ($staffPhone) {
            $confirmation = implode("\n", [
                "Automation resumed for booking #{$bookingId}",
                "Guest: {$booking->guest_name}",
                "Suite: {$booking->suite_name}",
                "Previous state: {$previousState}",
                "New state: waiting_preferences",
            ]);

            try {
                $twoChat->sendMessage($staffPhone, $confirmation);
                WhatsappMessage::create([
                    'direction' => 'outbound',
                    'phone_number' => $staffPhone,
                    'message_body' => $confirmation,
                    'agent_source' => 'done_handler',
                    'booking_id' => $booking->id,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('DONE confirmation failed', ['error' => $e->getMessage()]);
            }
        }

        // Send welcome message now that the booking is unblocked (if not already sent)
        $welcomeAlreadySent = WhatsappMessage::where('booking_id', $booking->id)
            ->where('direction', 'outbound')
            ->where('agent_source', 'lodgify_sync')
            ->exists();

        if (!$welcomeAlreadySent && $booking->guest_phone && $booking->booking_status === 'confirmed') {
            try {
                $response = (new GuestReplyAgent($booking))->prompt(
                    'Generate a warm welcome message for this guest. Introduce yourself as the concierge and let them know you are available on WhatsApp. Ask about their estimated arrival time.'
                );
                $message = (string) $response;

                $result = NighttimeQueue::sendOrQueue($twoChat, $booking->guest_phone, $message, $booking->id, 'done_handler');

                SystemLog::create([
                    'agent' => 'done_handler',
                    'action' => 'welcome_sent',
                    'booking_id' => $booking->id,
                    'status' => 'success',
                ]);
            } catch (\Throwable $e) {
                Log::error('DONE welcome message failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
            }
        }

        return WebResponse::json(null, 'DONE processed');
    }

    /**
     * Expense input: manager sends "expense [amount] [category] [description]" via WhatsApp.
     * Uses Claude to parse flexible formats. Logs to Transaction. Sends confirmation.
     */
    protected function handleExpenseInput(string $text, string $phone, TwoChatService $twoChat): JsonResponse
    {
        $start = microtime(true);

        try {
            $result = (new ExpenseParserAgent())->prompt($text);
            $parsed = $result->toArray();

            if (empty($parsed['amount']) || $parsed['amount'] <= 0) {
                $twoChat->sendMessage($phone, "Could not parse expense amount. Format: expense [amount] [category] [description]\n\nExample: expense 1200 food weekly market supplies");
                return WebResponse::json(null, 'Expense parse failed');
            }

            $transaction = Transaction::create([
                'type' => 'expense',
                'category' => $parsed['category'] ?? 'other',
                'description' => $parsed['description'] ?? $text,
                'amount' => $parsed['amount'],
                'currency' => 'MAD',
                'transaction_date' => now()->toDateString(),
                'recorded_by' => 'manager',
            ]);

            $categoryInferred = !empty($parsed['category_inferred']) && $parsed['category_inferred'];

            $confirmation = implode("\n", [
                "Expense logged:",
                "  Amount: {$parsed['amount']} MAD",
                "  Category: {$parsed['category']}",
                "  Description: {$parsed['description']}",
                "",
                $categoryInferred
                    ? "Category was inferred. Is this correct? Reply NO to cancel within 5 minutes."
                    : "Logged successfully.",
            ]);

            $sendResult = $twoChat->sendMessage($phone, $confirmation);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $phone,
                'message_body' => $confirmation,
                'agent_source' => 'expense_input',
                'twochat_message_id' => $sendResult['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            // Store transaction ID in cache for 5-minute cancellation window
            if ($categoryInferred) {
                cache()->put("expense_cancel:{$phone}", $transaction->id, now()->addMinutes(5));
            }

            SystemLog::create([
                'agent' => 'expense_input',
                'action' => 'expense_logged',
                'status' => 'success',
                'payload' => [
                    'amount' => $parsed['amount'],
                    'category' => $parsed['category'],
                    'transaction_id' => $transaction->id,
                ],
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            return WebResponse::json(null, 'Expense logged');
        } catch (\Throwable $e) {
            Log::error('Expense input failed', ['error' => $e->getMessage()]);

            try {
                $twoChat->sendMessage($phone, "Failed to process expense. Please try again.\n\nFormat: expense [amount] [category] [description]");
            } catch (\Throwable) {
                // Silently fail — original error is already logged
            }

            SystemLog::create([
                'agent' => 'expense_input',
                'action' => 'expense_logged',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            return WebResponse::json(null, 'Expense failed');
        }
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
