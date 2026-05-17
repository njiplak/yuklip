<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Setting;
use App\Service\Lodgify\LodgifyService;
use App\Utils\WebResponse;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OccupancyController extends Controller
{
    /** Booking statuses that contribute to occupied nights. */
    private const OCCUPIED_STATUSES = ['confirmed', 'checked_in', 'checked_out'];

    /** Cache TTLs — Lodgify property counts don't change minute-to-minute. */
    private const PROPERTY_COUNT_TTL_SECONDS = 86400;     // 24h
    private const BLOCKED_NIGHTS_TTL_SECONDS = 1800;      // 30 min

    public function __construct(private readonly LodgifyService $lodgify)
    {
    }

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

        $totalSuites = $this->resolveTotalSuites();
        $grossNights = $totalSuites * $daysInPeriod;
        $blockedNights = $this->blockedNightsFromLodgify($from, $periodEnd);
        $availableNights = max(0, $grossNights - $blockedNights);

        $occupiedNights = $this->occupiedNights($from, $periodEnd);

        $rate = $availableNights > 0
            ? round(min(1.0, $occupiedNights / $availableNights), 4)
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
     * Real suite count comes from Lodgify (cached daily). Falls back to the
     * `total_suites` Setting (then the literal default 4) when Lodgify is
     * unreachable so the endpoint never breaks because of an outage.
     */
    private function resolveTotalSuites(): int
    {
        $fromLodgify = Cache::remember(
            'lodgify:active_properties_count',
            self::PROPERTY_COUNT_TTL_SECONDS,
            function (): ?int {
                try {
                    $count = $this->lodgify->countActiveProperties();
                    return $count > 0 ? $count : null;
                } catch (\Throwable $e) {
                    Log::warning('Lodgify property count failed', [
                        'error' => $e->getMessage(),
                    ]);
                    return null;
                }
            },
        );

        if ($fromLodgify !== null) {
            return $fromLodgify;
        }

        return (int) (Setting::where('key', 'total_suites')->value('value') ?? 4);
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

    /**
     * Sum owner-blocked nights across all Lodgify properties in [from, periodEnd].
     * "Blocked" means a Lodgify period flagged with closed_period — owner blocks,
     * maintenance, etc. — which should NOT count toward available capacity.
     *
     * Returns 0 on any Lodgify error so an outage degrades to "trust gross
     * capacity" rather than breaking the endpoint.
     */
    private function blockedNightsFromLodgify(CarbonInterface $from, CarbonInterface $periodEnd): int
    {
        if ($from->gt($periodEnd)) {
            return 0;
        }

        $cacheKey = sprintf(
            'lodgify:blocked_nights:%s_%s',
            $from->toDateString(),
            $periodEnd->toDateString(),
        );

        return (int) Cache::remember(
            $cacheKey,
            self::BLOCKED_NIGHTS_TTL_SECONDS,
            function () use ($from, $periodEnd): int {
                try {
                    $properties = $this->lodgify->listProperties();
                } catch (\Throwable $e) {
                    Log::warning('Lodgify property list failed (blocked-nights skipped)', [
                        'error' => $e->getMessage(),
                    ]);
                    return 0;
                }

                $upperBoundExclusive = $periodEnd->copy()->addDay();
                $blocked = 0;

                foreach ($properties as $property) {
                    $propertyId = $property['id'] ?? null;
                    if (!$propertyId) {
                        continue;
                    }

                    try {
                        $availability = $this->lodgify->getPropertyAvailability(
                            $propertyId,
                            $from,
                            $periodEnd,
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Lodgify availability fetch failed', [
                            'property_id' => $propertyId,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }

                    foreach ($availability as $entry) {
                        $periods = $entry['periods'] ?? [];
                        foreach ($periods as $period) {
                            if (empty($period['closed_period'])) {
                                continue;
                            }
                            $start = isset($period['start']) ? Carbon::parse($period['start']) : null;
                            $end = isset($period['end']) ? Carbon::parse($period['end']) : null;
                            if (!$start || !$end) {
                                continue;
                            }

                            $nightStart = $start->isAfter($from) ? $start->copy() : $from->copy();
                            $nightEnd = $end->lt($upperBoundExclusive) ? $end->copy() : $upperBoundExclusive->copy();
                            if ($nightStart->lt($nightEnd)) {
                                $blocked += (int) $nightStart->diffInDays($nightEnd);
                            }
                        }
                    }
                }

                return $blocked;
            },
        );
    }
}
