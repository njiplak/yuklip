<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Setting;
use App\Utils\WebResponse;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class OccupancyController extends Controller
{
    /** Booking statuses that contribute to occupied nights. */
    private const OCCUPIED_STATUSES = ['confirmed', 'checked_in', 'checked_out'];

    public function index()
    {
        $month = (int) request('month', now()->month);
        $year = (int) request('year', now()->year);

        $from = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $from->copy()->endOfMonth()->startOfDay();
        $today = now()->startOfDay();

        // Cap the active window at today — no future-night counting.
        $periodEnd = $today->lt($endOfMonth) ? $today : $endOfMonth;
        $daysInPeriod = $from->lte($periodEnd) ? ((int) $from->diffInDays($periodEnd) + 1) : 0;

        $totalSuites = (int) (Setting::where('key', 'total_suites')->value('value') ?? 4);
        $availableNights = $totalSuites * $daysInPeriod;

        $occupiedNights = $this->occupiedNights($from, $periodEnd);

        $rate = $availableNights > 0
            ? round($occupiedNights / $availableNights, 4)
            : 0.0;

        $result = [
            'period' => [
                'month' => $from->format('F'),
                'year' => $year,
            ],
            'rate' => $rate,
            'occupied_nights' => $occupiedNights,
            'available_nights' => $availableNights,
            'currency' => Setting::where('key', 'currency')->value('value') ?? 'EUR',
        ];

        return WebResponse::json($result, 'Occupancy retrieved.');
    }

    /**
     * Sum the night-overlap between active bookings and the [from, periodEnd] window.
     * Nights span [check_in, check_out) — check_out day is departure (no night).
     */
    private function occupiedNights(CarbonInterface $from, CarbonInterface $periodEnd): int
    {
        if ($from->gt($periodEnd)) {
            return 0;
        }

        $upperBoundExclusive = $periodEnd->copy()->addDay();

        $bookings = Booking::whereIn('booking_status', self::OCCUPIED_STATUSES)
            ->whereDate('check_in', '<', $upperBoundExclusive->toDateString())
            ->whereDate('check_out', '>', $from->toDateString())
            ->get(['check_in', 'check_out']);

        $total = 0;
        foreach ($bookings as $b) {
            $nightStart = $b->check_in->isAfter($from) ? $b->check_in->copy() : $from->copy();
            $nightEnd = $b->check_out->lt($upperBoundExclusive) ? $b->check_out->copy() : $upperBoundExclusive->copy();

            if ($nightStart->lt($nightEnd)) {
                $total += (int) $nightStart->diffInDays($nightEnd);
            }
        }

        return $total;
    }
}
