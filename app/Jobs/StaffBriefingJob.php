<?php

namespace App\Jobs;

use App\Ai\Agents\StaffBriefingAgent;
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

class StaffBriefingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(TwoChatService $twoChat): void
    {
        $today = Carbon::today();

        $arrivals = Booking::where('check_in', $today)
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->get()
            ->map(fn ($b) => [
                'guest_name' => $b->guest_name,
                'suite_name' => $b->suite_name,
                'num_guests' => $b->num_guests,
                'guest_nationality' => $b->guest_nationality,
                'special_requests' => implode(' | ', array_filter([
                    $b->pref_arrival_time ? "Arrival: {$b->pref_arrival_time}" : null,
                    $b->pref_bed_type ? "Bed: {$b->pref_bed_type}" : null,
                    $b->pref_airport_transfer ? "Transfer: {$b->pref_airport_transfer}" : null,
                    $b->pref_special_requests && $b->pref_special_requests !== 'none' ? $b->pref_special_requests : null,
                    $b->special_requests,
                ])) ?: null,
            ])
            ->all();

        $departures = Booking::where('check_out', $today)
            ->whereIn('booking_status', ['checked_in', 'checked_out'])
            ->get()
            ->map(fn ($b) => $b->only(['guest_name', 'suite_name']))
            ->all();

        if (empty($arrivals) && empty($departures)) {
            SystemLog::create([
                'agent' => 'staff_briefing',
                'action' => 'skipped',
                'status' => 'skipped',
                'payload' => ['reason' => 'no_arrivals_or_departures'],
            ]);
            return;
        }

        $staffNumber = config('whatsapp.staff_phone_number');

        if (!$staffNumber) {
            Log::error('StaffBriefingJob: STAFF_WHATSAPP_NUMBER is not configured');
            SystemLog::create([
                'agent' => 'staff_briefing',
                'action' => 'briefing_dispatched',
                'status' => 'failed',
                'error_message' => 'STAFF_WHATSAPP_NUMBER not configured',
            ]);
            return;
        }

        $start = microtime(true);

        try {
            $response = (new StaffBriefingAgent($arrivals, $departures))->prompt('Generate the daily briefing.');
            $briefing = (string) $response;

            PushNotificationService::dailyBriefing(count($arrivals), count($departures));

            $result = $twoChat->sendMessage($staffNumber, $briefing);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffNumber,
                'message_body' => $briefing,
                'agent_source' => 'staff_briefing',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'staff_briefing',
                'action' => 'briefing_dispatched',
                'status' => 'success',
                'payload' => ['arrivals' => count($arrivals), 'departures' => count($departures)],
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::error('StaffBriefingJob failed', ['error' => $e->getMessage()]);

            SystemLog::create([
                'agent' => 'staff_briefing',
                'action' => 'briefing_dispatched',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        }
    }
}
