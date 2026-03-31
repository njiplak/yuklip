<?php

namespace App\Jobs;

use App\Ai\Agents\GuestReplyAgent;
use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\WhatsappMessage;
use App\Service\WhatsApp\TwoChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FollowUpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_FOLLOW_UPS = 2;
    private const SILENCE_HOURS = 12;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(TwoChatService $twoChat): void
    {
        $bookings = Booking::whereIn('conversation_state', ['waiting_preferences', 'preferences_partial'])
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->where('follow_up_count', '<', self::MAX_FOLLOW_UPS)
            ->where('guest_phone', '!=', '')
            ->get();

        foreach ($bookings as $booking) {
            $this->processBooking($booking, $twoChat);
        }
    }

    protected function processBooking(Booking $booking, TwoChatService $twoChat): void
    {
        // Check if the guest has replied recently — no follow-up needed
        $lastInbound = WhatsappMessage::where('booking_id', $booking->id)
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->first();

        $lastOutbound = WhatsappMessage::where('booking_id', $booking->id)
            ->where('direction', 'outbound')
            ->latest('created_at')
            ->first();

        // No outbound message yet (welcome not sent) — skip
        if (!$lastOutbound) {
            return;
        }

        // Guest replied after our last message — no follow-up needed
        if ($lastInbound && $lastOutbound && $lastInbound->created_at->isAfter($lastOutbound->created_at)) {
            return;
        }

        // Our last message was less than SILENCE_HOURS ago — too early to follow up
        if ($lastOutbound->created_at->diffInHours(now()) < self::SILENCE_HOURS) {
            return;
        }

        $start = microtime(true);

        try {
            $followUpNumber = $booking->follow_up_count + 1;

            $prompt = $followUpNumber === 1
                ? 'The guest has not replied to your previous message. Send a gentle, friendly follow-up. Ask if they received your message and if they could share their arrival time so you can prepare for their welcome. Keep it very short and warm — one or two sentences.'
                : 'The guest has not replied to two messages now. Send a final gentle follow-up. Let them know you are here whenever they are ready, and mention they can also reach the riad directly by phone if they prefer. Do not pressure them. Keep it very short.';

            $response = (new GuestReplyAgent($booking))->prompt($prompt);
            $message = (string) $response;

            $result = $twoChat->sendMessage($booking->guest_phone, $message);

            WhatsappMessage::create([
                'booking_id' => $booking->id,
                'direction' => 'outbound',
                'phone_number' => $booking->guest_phone,
                'message_body' => $message,
                'agent_source' => 'follow_up',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            $booking->update(['follow_up_count' => $followUpNumber]);

            SystemLog::create([
                'agent' => 'follow_up',
                'action' => 'follow_up_sent',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => ['follow_up_number' => $followUpNumber],
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            // After max follow-ups, escalate to manager
            if ($followUpNumber >= self::MAX_FOLLOW_UPS) {
                $this->escalateToManager($booking, $twoChat);
            }
        } catch (\Throwable $e) {
            Log::error('Follow-up failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);

            SystemLog::create([
                'agent' => 'follow_up',
                'action' => 'follow_up_sent',
                'booking_id' => $booking->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        }
    }

    protected function escalateToManager(Booking $booking, TwoChatService $twoChat): void
    {
        $staffNumber = config('whatsapp.staff_phone_number');

        if (!$staffNumber) {
            return;
        }

        $booking->update(['conversation_state' => 'handover_human']);

        $alert = implode("\n", [
            "ESCALATION: Guest not responding",
            "",
            "Guest: {$booking->guest_name}",
            "Suite: {$booking->suite_name}",
            "Phone: {$booking->guest_phone}",
            "Check-in: {$booking->check_in->format('d M Y')}",
            "",
            "2 follow-ups sent with no reply. Automated messaging paused. Please contact the guest directly.",
        ]);

        try {
            $twoChat->sendMessage($staffNumber, $alert);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffNumber,
                'message_body' => $alert,
                'agent_source' => 'follow_up',
                'booking_id' => $booking->id,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'follow_up',
                'action' => 'escalated_to_manager',
                'booking_id' => $booking->id,
                'status' => 'success',
            ]);
        } catch (\Throwable $e) {
            Log::error('Escalation alert failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
        }
    }
}
