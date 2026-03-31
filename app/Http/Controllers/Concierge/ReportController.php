<?php

namespace App\Http\Controllers\Concierge;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\UpsellLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class ReportController extends Controller
{
    private const NUM_SUITES = 4;

    public function index()
    {
        return Inertia::render('concierge/report/index');
    }

    public function fetch(): JsonResponse
    {
        $from = request('from', now()->startOfMonth()->toDateString());
        $to = request('to', now()->toDateString());

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'summary' => $this->buildSummary($fromDate, $toDate),
            'revenue_by_suite' => $this->revenueByField($fromDate, $toDate, 'suite_name'),
            'revenue_by_source' => $this->revenueByField($fromDate, $toDate, 'booking_source'),
            'upsell' => $this->upsellMetrics($fromDate, $toDate),
        ]);
    }

    protected function buildSummary(Carbon $from, Carbon $to): array
    {
        $bookings = Booking::where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where('booking_status', '!=', 'cancelled');

        $totalBookings = $bookings->count();

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

        $netRevenue = $totalRevenue - $totalExpenses;

        $occupancy = $this->calculateOccupancy($from, $to);

        $cancellations = Booking::where('booking_status', 'cancelled')
            ->where('updated_at', '>=', $from)
            ->where('updated_at', '<=', $to)
            ->count();

        return [
            'total_bookings' => $totalBookings,
            'accommodation_revenue' => round($accommodationRevenue, 2),
            'upsell_revenue' => round($upsellRevenue, 2),
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_revenue' => round($netRevenue, 2),
            'occupancy_rate' => $occupancy,
            'cancellations' => $cancellations,
        ];
    }

    protected function calculateOccupancy(Carbon $from, Carbon $to): float
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

    protected function revenueByField(Carbon $from, Carbon $to, string $field): array
    {
        return Booking::where('booking_status', '!=', 'cancelled')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where('total_amount', '>', 0)
            ->selectRaw("{$field} as label, SUM(total_amount) as total, COUNT(*) as count")
            ->groupBy($field)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => round((float) $row->total, 2),
                'count' => $row->count,
            ])
            ->all();
    }

    protected function upsellMetrics(Carbon $from, Carbon $to): array
    {
        $logs = UpsellLog::where('sent_at', '>=', $from)
            ->where('sent_at', '<=', $to);

        $total = (clone $logs)->count();
        $accepted = (clone $logs)->where('outcome', 'accepted')->count();
        $declined = (clone $logs)->where('outcome', 'declined')->count();
        $pending = (clone $logs)->where('outcome', 'pending')->count();
        $revenue = (clone $logs)->where('outcome', 'accepted')->sum('revenue_generated');

        return [
            'total_sent' => $total,
            'accepted' => $accepted,
            'declined' => $declined,
            'pending' => $pending,
            'conversion_rate' => $total > 0 ? round(($accepted / $total) * 100, 1) : 0,
            'revenue' => round((float) $revenue, 2),
        ];
    }

    public function export(): JsonResponse
    {
        $from = request('from', now()->startOfMonth()->toDateString());
        $to = request('to', now()->toDateString());

        $transactions = Transaction::where('transaction_date', '>=', $from)
            ->where('transaction_date', '<=', $to)
            ->orderBy('transaction_date')
            ->get(['transaction_date', 'type', 'category', 'description', 'amount', 'currency', 'recorded_by']);

        return response()->json($transactions);
    }
}
