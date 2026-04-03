<?php

namespace App\Jobs;

use App\Ai\Agents\CancellationRecoveryAgent;
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

class CancellationRecoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $bookingId)
    {
        $this->onQueue('agents');
    }

    public function handle(TwoChatService $twoChat): void
    {
        $booking = Booking::find($this->bookingId);

        if (!$booking || $booking->booking_status !== 'cancelled') {
            return;
        }

        $staffPhone = config('whatsapp.staff_phone_number');
        $ownerPhone = config('whatsapp.owner_phone_number');

        $recipients = array_unique(array_filter([$staffPhone, $ownerPhone]));

        if (empty($recipients)) {
            return;
        }

        $start = microtime(true);

        try {
            $response = (new CancellationRecoveryAgent($booking))->prompt('Generate the recovery action plan.');
            $plan = (string) $response;

            $header = implode("\n", [
                "RECOVERY PLAN",
                "Cancelled: {$booking->guest_name} | {$booking->suite_name}",
                "Dates: {$booking->check_in->format('d M')} - {$booking->check_out->format('d M')}",
                "Lost: {$booking->total_amount} {$booking->currency}",
                "",
            ]);

            $message = $header . $plan;

            foreach ($recipients as $phone) {
                $result = $twoChat->sendMessage($phone, $message);

                WhatsappMessage::create([
                    'booking_id' => $booking->id,
                    'direction' => 'outbound',
                    'phone_number' => $phone,
                    'message_body' => $message,
                    'agent_source' => 'cancellation_recovery',
                    'twochat_message_id' => $result['message_uuid'] ?? null,
                    'sent_at' => now(),
                ]);
            }

            SystemLog::create([
                'agent' => 'cancellation_recovery',
                'action' => 'recovery_plan_sent',
                'booking_id' => $booking->id,
                'status' => 'success',
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::error('CancellationRecoveryJob failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);

            SystemLog::create([
                'agent' => 'cancellation_recovery',
                'action' => 'recovery_plan_sent',
                'booking_id' => $booking->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        }
    }
}
