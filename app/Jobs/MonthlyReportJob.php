<?php

namespace App\Jobs;

use App\Exports\MonthlyReportExport;
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
use Illuminate\Support\Facades\Storage;

class MonthlyReportJob implements ShouldQueue
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
                'agent' => 'monthly_report',
                'action' => 'skipped',
                'status' => 'skipped',
                'payload' => ['reason' => 'no_staff_phone_configured'],
            ]);

            return;
        }

        $start = microtime(true);

        // Report covers the previous calendar month
        $to = Carbon::today()->subMonth()->endOfMonth();
        $from = $to->copy()->startOfMonth();

        try {
            $metrics = $this->calculateMetrics($from, $to);

            // Generate Excel file
            $filename = 'reports/monthly-' . $from->format('Y-m') . '.xlsx';
            (new MonthlyReportExport($from, $to, $metrics))->store($filename, 'public');

            $downloadUrl = Storage::disk('public')->url($filename);

            // Send WhatsApp summary
            $message = $this->formatMessage($metrics, $from);
            $result = $twoChat->sendMessage($staffPhone, $message);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffPhone,
                'message_body' => $message,
                'agent_source' => 'monthly_report',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            // Send download link as separate message
            $linkMessage = "Excel report: {$downloadUrl}";
            $linkResult = $twoChat->sendMessage($staffPhone, $linkMessage);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffPhone,
                'message_body' => $linkMessage,
                'agent_source' => 'monthly_report',
                'twochat_message_id' => $linkResult['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'monthly_report',
                'action' => 'report_sent',
                'status' => 'success',
                'payload' => array_merge($metrics, ['excel_file' => $filename]),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::error('MonthlyReportJob failed', ['error' => $e->getMessage()]);

            SystemLog::create([
                'agent' => 'monthly_report',
                'action' => 'report_sent',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        }
    }

    private function calculateMetrics(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->copy()->startOfDay();
        $toDate = $to->copy()->endOfDay();

        // Bookings
        $totalBookings = Booking::where('created_at', '>=', $fromDate)
            ->where('created_at', '<=', $toDate)
            ->where('booking_status', '!=', 'cancelled')
            ->count();

        $cancellations = Booking::where('booking_status', 'cancelled')
            ->where('updated_at', '>=', $fromDate)
            ->where('updated_at', '<=', $toDate)
            ->count();

        // Revenue
        $income = Transaction::where('type', 'income')
            ->where('transaction_date', '>=', $from->toDateString())
            ->where('transaction_date', '<=', $to->toDateString());

        $accommodationRevenue = (clone $income)->where('category', 'accommodation')->sum('amount');
        $upsellRevenue = (clone $income)->where('category', 'upsell')->sum('amount');
        $totalRevenue = (clone $income)->sum('amount');

        // Expenses
        $totalExpenses = Transaction::where('type', 'expense')
            ->where('transaction_date', '>=', $from->toDateString())
            ->where('transaction_date', '<=', $to->toDateString())
            ->sum('amount');

        // Upsell
        $upsellsSent = UpsellLog::where('sent_at', '>=', $fromDate)
            ->where('sent_at', '<=', $toDate)
            ->count();

        $upsellsAccepted = UpsellLog::where('sent_at', '>=', $fromDate)
            ->where('sent_at', '<=', $toDate)
            ->where('outcome', 'accepted')
            ->count();

        $upsellConversion = $upsellsSent > 0
            ? round(($upsellsAccepted / $upsellsSent) * 100, 1)
            : 0;

        // Occupancy
        $occupancy = $this->calculateOccupancy($from, $to);

        // Revenue by suite
        $revenueBySuite = Booking::where('booking_status', '!=', 'cancelled')
            ->where('created_at', '>=', $fromDate)
            ->where('created_at', '<=', $toDate)
            ->where('total_amount', '>', 0)
            ->selectRaw('suite_name as label, SUM(total_amount) as total, COUNT(*) as count')
            ->groupBy('suite_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => round((float) $row->total, 2),
                'count' => $row->count,
            ])
            ->all();

        return [
            'total_bookings' => $totalBookings,
            'cancellations' => $cancellations,
            'accommodation_revenue' => round((float) $accommodationRevenue, 2),
            'upsell_revenue' => round((float) $upsellRevenue, 2),
            'total_revenue' => round((float) $totalRevenue, 2),
            'total_expenses' => round((float) $totalExpenses, 2),
            'net_revenue' => round((float) $totalRevenue - (float) $totalExpenses, 2),
            'upsells_sent' => $upsellsSent,
            'upsells_accepted' => $upsellsAccepted,
            'upsell_conversion' => $upsellConversion,
            'occupancy_rate' => $occupancy,
            'revenue_by_suite' => $revenueBySuite,
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

    private function formatMessage(array $m, Carbon $from): string
    {
        $lines = [
            "MONTHLY REPORT — {$from->format('F Y')}",
            "Riad Larbi Khalis",
            "",
            "BOOKINGS: {$m['total_bookings']} (cancelled: {$m['cancellations']})",
            "",
            "REVENUE",
            "  Accommodation: {$m['accommodation_revenue']} MAD",
            "  Upsell: {$m['upsell_revenue']} MAD",
            "  Total: {$m['total_revenue']} MAD",
            "",
            "EXPENSES: {$m['total_expenses']} MAD",
            "NET: {$m['net_revenue']} MAD",
            "",
            "OCCUPANCY: {$m['occupancy_rate']}%",
            "",
            "UPSELL: {$m['upsells_accepted']}/{$m['upsells_sent']} accepted ({$m['upsell_conversion']}%)",
        ];

        if (!empty($m['revenue_by_suite'])) {
            $lines[] = "";
            $lines[] = "BY SUITE";
            foreach ($m['revenue_by_suite'] as $suite) {
                $lines[] = "  {$suite['label']}: {$suite['total']} MAD ({$suite['count']} bookings)";
            }
        }

        $lines[] = "";
        $lines[] = "Full Excel report sent separately.";

        return implode("\n", $lines);
    }
}
