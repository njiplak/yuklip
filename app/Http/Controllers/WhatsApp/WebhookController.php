<?php

namespace App\Http\Controllers\WhatsApp;

use App\Ai\Agents\GuestReplyAgent;
use App\Ai\Agents\UpsellReplyAgent;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\UpsellLog;
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
        $webhookSecret = config('whatsapp.webhook_secret');

        if (!$webhookSecret) {
            Log::warning('WhatsApp webhook secret not configured — rejecting request');
            return response()->json(['error' => 'Webhook secret not configured'], 403);
        }

        if ($request->header('X-Webhook-Secret') !== $webhookSecret) {
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

        if ($this->isPendingUpsellReply($booking)) {
            return $this->handleUpsellReply($booking, $text, $twoChat);
        }

        return $this->handleGuestReply($booking, $text, $twoChat);
    }

    protected function handleGuestReply(Booking $booking, string $text, TwoChatService $twoChat): JsonResponse
    {
        $start = microtime(true);

        try {
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

            if ($classification === 'accepted' && $offer->price) {
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
