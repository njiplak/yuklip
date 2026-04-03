<?php

namespace App\Service\WhatsApp;

use App\Models\WhatsappMessage;
use Carbon\Carbon;

class NighttimeQueue
{
    /** Nighttime window: 10 PM to 7 AM Africa/Casablanca */
    private const NIGHT_START_HOUR = 22;
    private const NIGHT_END_HOUR = 7;
    private const SEND_HOUR = 8;
    private const TIMEZONE = 'Africa/Casablanca';

    /**
     * Check if current time is within the nighttime window.
     */
    public static function isNighttime(): bool
    {
        $now = Carbon::now(self::TIMEZONE);
        $hour = $now->hour;

        return $hour >= self::NIGHT_START_HOUR || $hour < self::NIGHT_END_HOUR;
    }

    /**
     * Get the next 8 AM send time.
     */
    public static function nextSendTime(): Carbon
    {
        $now = Carbon::now(self::TIMEZONE);

        // If it's before 8 AM today, send at 8 AM today
        if ($now->hour < self::SEND_HOUR) {
            return $now->copy()->setTime(self::SEND_HOUR, 0, 0);
        }

        // Otherwise, send at 8 AM tomorrow
        return $now->copy()->addDay()->setTime(self::SEND_HOUR, 0, 0);
    }

    /**
     * Send immediately if daytime, or queue for 8 AM if nighttime.
     *
     * Returns the WhatsappMessage record (either sent or queued).
     * Staff briefings should ALWAYS be sent immediately regardless of time — those
     * callers should use TwoChatService::sendMessage directly.
     */
    public static function sendOrQueue(
        TwoChatService $twoChat,
        string $phone,
        string $message,
        ?int $bookingId = null,
        string $agentSource = 'guest_reply',
    ): WhatsappMessage {
        if (!self::isNighttime()) {
            // Daytime — send immediately
            $result = $twoChat->sendMessage($phone, $message);

            return WhatsappMessage::create([
                'booking_id' => $bookingId,
                'direction' => 'outbound',
                'phone_number' => $phone,
                'message_body' => $message,
                'agent_source' => $agentSource,
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);
        }

        // Nighttime — queue for 8 AM
        return WhatsappMessage::create([
            'booking_id' => $bookingId,
            'direction' => 'outbound',
            'phone_number' => $phone,
            'message_body' => $message,
            'agent_source' => $agentSource,
            'pending_send_at' => self::nextSendTime(),
            'sent_at' => null,
        ]);
    }
}
