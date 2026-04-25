<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\Transaction;
use App\Utils\WebResponse;
use Carbon\Carbon;

class FinancialReportController extends Controller
{
    /** Booking statuses that count toward the "stays" tally for a month. */
    private const STAY_STATUSES = ['confirmed', 'checked_in', 'checked_out'];

    public function index()
    {
        $month = (int) request('month', now()->month);
        $year = (int) request('year', now()->year);

        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth()->endOfDay();

        $totalRevenue = $this->totalRevenue($from, $to);
        $accommodationRevenue = $this->categoryRevenue($from, $to, 'accommodation');
        $upsellRevenue = $this->categoryRevenue($from, $to, 'upsell');

        $expenses = round((float) Transaction::where('type', 'expense')
            ->where('transaction_date', '>=', $from->toDateString())
            ->where('transaction_date', '<=', $to->toDateString())
            ->sum('amount'), 2);

        $conciergeFeeRate = (float) (Setting::where('key', 'concierge_fee_rate')->value('value') ?? 15);
        $conciergeFee = round($totalRevenue * $conciergeFeeRate / 100, 2);
        $netProfit = round($totalRevenue - $expenses - $conciergeFee, 2);

        $traditionalRate = 20;
        $traditionalCost = round($totalRevenue * $traditionalRate / 100, 2);
        $savings = round($traditionalCost - $conciergeFee, 2);

        $previousFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $previousTo = $previousFrom->copy()->endOfMonth()->endOfDay();
        $previousTotalRevenue = $this->totalRevenue($previousFrom, $previousTo);

        $revenueDeltaPct = $previousTotalRevenue > 0
            ? round(($totalRevenue - $previousTotalRevenue) / $previousTotalRevenue, 4)
            : null;

        $result = [
            'period' => [
                'month' => $from->format('F'),
                'year' => $year,
            ],
            'savings' => [
                'amount' => $savings,
                'traditional_rate' => $traditionalRate,
            ],
            'summary' => [
                'accommodation_revenue' => $accommodationRevenue,
                'upsell_revenue' => $upsellRevenue,
                'expenses' => $expenses,
                'concierge_fee' => $conciergeFee,
                'concierge_fee_rate' => $conciergeFeeRate,
                'net_profit' => $netProfit,
                'previous_total_revenue' => $previousTotalRevenue,
                'revenue_delta_pct' => $revenueDeltaPct,
                'stays_count' => $this->staysCount($from, $to),
            ],
            'currency' => Setting::where('key', 'currency')->value('value') ?? 'EUR',
            'weekly_reports' => $this->buildWeeklyReports($from, $to),
        ];

        return WebResponse::json($result, 'Financial report retrieved.');
    }

    private function categoryRevenue(Carbon $from, Carbon $to, string $category): float
    {
        return round((float) Transaction::where('type', 'income')
            ->where('category', $category)
            ->where('transaction_date', '>=', $from->toDateString())
            ->where('transaction_date', '<=', $to->toDateString())
            ->sum('amount'), 2);
    }

    private function totalRevenue(Carbon $from, Carbon $to): float
    {
        return round($this->categoryRevenue($from, $to, 'accommodation')
            + $this->categoryRevenue($from, $to, 'upsell'), 2);
    }

    /**
     * Stays whose date range overlaps the month. A booking is counted if
     * [check_in, check_out) intersects [from, to+1day).
     */
    private function staysCount(Carbon $from, Carbon $to): int
    {
        $upperBoundExclusive = $to->copy()->addDay()->toDateString();

        return Booking::whereIn('booking_status', self::STAY_STATUSES)
            ->whereDate('check_in', '<', $upperBoundExclusive)
            ->whereDate('check_out', '>', $from->toDateString())
            ->count();
    }

    protected function buildWeeklyReports(Carbon $from, Carbon $to): array
    {
        $reports = [];
        $weekStart = $from->copy()->startOfWeek(Carbon::MONDAY);

        if ($weekStart->lt($from)) {
            $weekStart = $from->copy();
        }

        while ($weekStart->lte($to) && $weekStart->lte(now())) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->min($to);

            $weekIncome = Transaction::where('type', 'income')
                ->where('transaction_date', '>=', $weekStart->toDateString())
                ->where('transaction_date', '<=', $weekEnd->toDateString())
                ->sum('amount');

            $reports[] = [
                'label' => 'Weekly report — ' . $weekStart->format('M j'),
                'subtitle' => 'Revenue · Upsells · Occupancy',
                'revenue' => round((float) $weekIncome, 2),
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
            ];

            $weekStart = $weekStart->copy()->next(Carbon::MONDAY);
        }

        return $reports;
    }
}
