<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Utils\WebResponse;
use Carbon\Carbon;

class FinancialReportController extends Controller
{
    public function index()
    {
        $month = (int) request('month', now()->month);
        $year = (int) request('year', now()->year);

        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth()->endOfDay();

        $income = Transaction::where('type', 'income')
            ->where('transaction_date', '>=', $from->toDateString())
            ->where('transaction_date', '<=', $to->toDateString());

        $accommodationRevenue = round((float) (clone $income)->where('category', 'accommodation')->sum('amount'), 2);
        $upsellRevenue = round((float) (clone $income)->where('category', 'upsell')->sum('amount'), 2);
        $totalRevenue = $accommodationRevenue + $upsellRevenue;

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
            ],
            'currency' => Setting::where('key', 'currency')->value('value') ?? 'EUR',
            'weekly_reports' => $this->buildWeeklyReports($from, $to),
        ];

        return WebResponse::json($result, 'Financial report retrieved.');
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
