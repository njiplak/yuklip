<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\UpsellLog;
use App\Models\WhatsappMessage;
use App\Service\WhatsApp\TwoChatService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WeeklySnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const NUM_SUITES = 4;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(TwoChatService $twoChat): void
    {
        $staffPhone = config('whatsapp.staff_phone_number');

        if (!$staffPhone) {
            SystemLog::create([
                'agent' => 'weekly_snapshot',
                'action' => 'skipped',
                'status' => 'skipped',
                'payload' => ['reason' => 'no_staff_phone_configured'],
            ]);

            return;
        }

        $start = microtime(true);
        $to = Carbon::today();
        $from = $to->copy()->subDays(7);

        try {
            $metrics = $this->calculateMetrics($from, $to);
            $message = $this->formatMessage($metrics, $from, $to);

            $result = $twoChat->sendMessage($staffPhone, $message);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffPhone,
                'message_body' => $message,
                'agent_source' => 'weekly_snapshot',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'weekly_snapshot',
                'action' => 'snapshot_sent',
                'status' => 'success',
                'payload' => $metrics,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::error('WeeklySnapshotJob failed', ['error' => $e->getMessage()]);

            SystemLog::create([
                'agent' => 'weekly_snapshot',
                'action' => 'snapshot_sent',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        }
    }

    private function calculateMetrics(Carbon $from, Carbon $to): array
    {
        $income = Transaction::where('type', 'income')
            ->where('transaction_date', '>=', $from->toDateString())
            ->where('transaction_date', '<=', $to->toDateString());

        $accommodationRevenue = (clone $income)->where('category', 'accommodation')->sum('amount');
        $upsellRevenue = (clone $income)->where('category', 'upsell')->sum('amount');
        $totalRevenue = (clone $income)->sum('amount');

        $totalExpenses = Transaction::where('type', 'expense')
            ->where('transaction_date', '>=', $from->toDateString())
            ->where('transaction_date', '<=', $to->toDateString())
            ->sum('amount');

        $newBookings = Booking::where('created_at', '>=', $from)
            ->where('created_at', '<=', $to->endOfDay())
            ->where('booking_status', '!=', 'cancelled')
            ->count();

        $cancellations = Booking::where('booking_status', 'cancelled')
            ->where('updated_at', '>=', $from)
            ->where('updated_at', '<=', $to->endOfDay())
            ->count();

        $upsellsSent = UpsellLog::where('sent_at', '>=', $from)
            ->where('sent_at', '<=', $to->endOfDay())
            ->count();

        $upsellsAccepted = UpsellLog::where('sent_at', '>=', $from)
            ->where('sent_at', '<=', $to->endOfDay())
            ->where('outcome', 'accepted')
            ->count();

        $occupancy = $this->calculateOccupancy($from, $to);

        return [
            'accommodation_revenue' => round((float) $accommodationRevenue, 2),
            'upsell_revenue' => round((float) $upsellRevenue, 2),
            'total_revenue' => round((float) $totalRevenue, 2),
            'total_expenses' => round((float) $totalExpenses, 2),
            'net_revenue' => round((float) $totalRevenue - (float) $totalExpenses, 2),
            'new_bookings' => $newBookings,
            'cancellations' => $cancellations,
            'upsells_sent' => $upsellsSent,
            'upsells_accepted' => $upsellsAccepted,
            'occupancy_rate' => $occupancy,
        ];
    }

    private function calculateOccupancy(Carbon $from, Carbon $to): float
    {
        $daysInPeriod = $from->diffInDays($to) + 1;
        $availableNights = self::NUM_SUITES * $daysInPeriod;

        if ($availableNights === 0) {
            return 0;
        }

        $bookings = Booking::where('booking_status', '!=', 'cancelled')
            ->where('check_in', '<', $to->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->get(['check_in', 'check_out']);

        $bookedNights = 0;

        foreach ($bookings as $booking) {
            $stayStart = $booking->check_in->max($from);
            $stayEnd = $booking->check_out->min($to);
            $nights = $stayStart->diffInDays($stayEnd);

            if ($nights > 0) {
                $bookedNights += $nights;
            }
        }

        return round(($bookedNights / $availableNights) * 100, 1);
    }

    private function formatMessage(array $m, Carbon $from, Carbon $to): string
    {
        $lines = [
            "WEEKLY SNAPSHOT",
            "{$from->format('d M')} — {$to->format('d M Y')}",
            "",
            "REVENUE",
            "  Accommodation: {$m['accommodation_revenue']} MAD",
            "  Upsell: {$m['upsell_revenue']} MAD",
            "  Total: {$m['total_revenue']} MAD",
            "",
            "EXPENSES: {$m['total_expenses']} MAD",
            "NET: {$m['net_revenue']} MAD",
            "",
            "BOOKINGS",
            "  New: {$m['new_bookings']}",
            "  Cancelled: {$m['cancellations']}",
            "",
            "UPSELL",
            "  Sent: {$m['upsells_sent']}",
            "  Accepted: {$m['upsells_accepted']}",
            "",
            "OCCUPANCY: {$m['occupancy_rate']}%",
        ];

        return implode("\n", $lines);
    }
}
