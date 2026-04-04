<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\WhatsappMessage;
use App\Service\PushNotificationService;
use App\Service\WhatsApp\TwoChatService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(TwoChatService $twoChat): void
    {
        $staffPhone = config('whatsapp.staff_phone_number');

        $issues = [];

        // 1. Check for failed agent actions in the last 24 hours
        $failedLogs = SystemLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->get();

        if ($failedLogs->isNotEmpty()) {
            $grouped = $failedLogs->groupBy('agent');
            foreach ($grouped as $agent => $logs) {
                $issues[] = "FAILED: {$agent} — {$logs->count()} failure(s) in 24h";
                foreach ($logs->take(3) as $log) {
                    $issues[] = "  → {$log->action}: {$log->error_message}";
                }
            }
        }

        // 2. Check for stuck bookings (waiting_preferences or preferences_partial for > 3 days)
        $stuckBookings = Booking::whereIn('conversation_state', ['waiting_preferences', 'preferences_partial'])
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->where('created_at', '<', now()->subDays(3))
            ->get();

        if ($stuckBookings->isNotEmpty()) {
            $issues[] = "";
            $issues[] = "STUCK BOOKINGS ({$stuckBookings->count()}):";
            foreach ($stuckBookings as $booking) {
                $age = $booking->created_at->diffInDays(now());
                $issues[] = "  → {$booking->guest_name} ({$booking->suite_name}) — state: {$booking->conversation_state}, {$age} days old";
            }
        }

        // 3. Check for bookings with check-in today but no welcome message sent
        $todayArrivals = Booking::where('check_in', Carbon::today())
            ->whereIn('booking_status', ['confirmed'])
            ->get();

        $noWelcome = $todayArrivals->filter(function ($booking) {
            return !WhatsappMessage::where('booking_id', $booking->id)
                ->where('direction', 'outbound')
                ->whereIn('agent_source', ['lodgify_sync', 'done_handler'])
                ->exists();
        });

        if ($noWelcome->isNotEmpty()) {
            $issues[] = "";
            $issues[] = "ARRIVING TODAY — NO WELCOME SENT ({$noWelcome->count()}):";
            foreach ($noWelcome as $booking) {
                $issues[] = "  → {$booking->guest_name} ({$booking->suite_name}) — state: {$booking->conversation_state}";
            }
        }

        // 4. Check for phone_missing bookings that haven't been resolved
        $phoneMissing = Booking::where('conversation_state', 'phone_missing')
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->get();

        if ($phoneMissing->isNotEmpty()) {
            $issues[] = "";
            $issues[] = "PHONE MISSING ({$phoneMissing->count()}):";
            foreach ($phoneMissing as $booking) {
                $issues[] = "  → {$booking->guest_name} ({$booking->suite_name}) — check-in: {$booking->check_in->format('d M')}";
            }
        }

        // 5. Check for pending nighttime messages that should have been sent
        $staleQueued = WhatsappMessage::where('direction', 'outbound')
            ->whereNull('sent_at')
            ->whereNotNull('pending_send_at')
            ->where('pending_send_at', '<', now()->subHours(2))
            ->count();

        if ($staleQueued > 0) {
            $issues[] = "";
            $issues[] = "STALE QUEUED MESSAGES: {$staleQueued} message(s) past their send time by >2 hours";
        }

        // Log the health check result
        $isHealthy = empty($issues);

        SystemLog::create([
            'agent' => 'orchestrator',
            'action' => 'health_check',
            'status' => $isHealthy ? 'success' : 'warning',
            'payload' => [
                'issues_count' => count(array_filter($issues, fn ($line) => !empty(trim($line)))),
                'failed_actions_24h' => $failedLogs->count(),
                'stuck_bookings' => $stuckBookings->count(),
                'no_welcome_today' => $noWelcome->count(),
                'phone_missing' => $phoneMissing->count(),
                'stale_queued' => $staleQueued,
            ],
        ]);

        // Only alert manager if there are issues
        if ($isHealthy) {
            return;
        }

        if (!$staffPhone) {
            return;
        }

        $message = implode("\n", array_merge(
            ["DAILY HEALTH CHECK — " . now()->format('d M Y')],
            [""],
            $issues,
        ));

        PushNotificationService::healthAlert(count($issues) . ' issue(s) detected — check system logs');

        try {
            $result = $twoChat->sendMessage($staffPhone, $message);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffPhone,
                'message_body' => $message,
                'agent_source' => 'orchestrator',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('HealthCheckJob alert failed', ['error' => $e->getMessage()]);
        }
    }
}
