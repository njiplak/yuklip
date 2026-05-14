<?php

namespace App\Service\Booking;

use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Derives lifecycle status for a booking — stay phase, day-of-stay, local time —
 * so AI prompts know whether the guest is pre-arrival, arriving today, in-stay,
 * checking out today, or already departed.
 *
 * Phase precedence on overlapping calendar dates (0-night day-use bookings):
 * arrival_day wins over departure_day.
 */
class StayContext
{
    public const PHASE_PRE_ARRIVAL = 'pre_arrival';
    public const PHASE_ARRIVAL_DAY = 'arrival_day';
    public const PHASE_IN_STAY = 'in_stay';
    public const PHASE_DEPARTURE_DAY = 'departure_day';
    public const PHASE_POST_STAY = 'post_stay';

    /**
     * @return array{
     *   now: CarbonInterface,
     *   local_time: string,
     *   timezone: string,
     *   phase: string,
     *   nights_total: int,
     *   total_days: int,
     *   is_day_use: bool,
     *   is_single_night: bool,
     *   day_of_stay: ?int,
     *   days_until_check_in: int,
     *   days_until_check_out: int,
     *   nights_remaining: int,
     * }
     */
    public static function compute(Booking $booking, ?CarbonInterface $now = null, ?string $timezone = null): array
    {
        $tz = $timezone ?? config('app.timezone', 'UTC');
        $nowLocal = ($now ? $now->copy() : Carbon::now())->setTimezone($tz);
        $today = $nowLocal->copy()->startOfDay();

        // check_in / check_out are date-only fields. Convert to a Y-m-d string
        // and re-anchor at midnight in the target timezone — otherwise the cast's
        // implicit timezone (app tz) shifts the calendar day when interpreted
        // in a different zone.
        $checkInDate = Carbon::parse($booking->check_in)->format('Y-m-d');
        $checkOutDate = Carbon::parse($booking->check_out)->format('Y-m-d');
        $checkIn = Carbon::createFromFormat('Y-m-d H:i:s', "{$checkInDate} 00:00:00", $tz);
        $checkOut = Carbon::createFromFormat('Y-m-d H:i:s', "{$checkOutDate} 00:00:00", $tz);

        // Defensive: if data is inverted (check_out before check_in), treat as 0-night.
        $nights = (int) max(0, round($checkIn->diffInDays($checkOut, false)));
        $totalDays = $nights === 0 ? 1 : $nights + 1;

        $phase = self::resolvePhase($today, $checkIn, $checkOut);

        $daysUntilCheckIn = (int) round($today->diffInDays($checkIn, false));
        $daysUntilCheckOut = (int) round($today->diffInDays($checkOut, false));

        $dayOfStay = match ($phase) {
            self::PHASE_ARRIVAL_DAY => 1,
            self::PHASE_DEPARTURE_DAY => $totalDays,
            self::PHASE_IN_STAY => (int) round($checkIn->diffInDays($today, false)) + 1,
            default => null,
        };

        $nightsRemaining = match ($phase) {
            self::PHASE_PRE_ARRIVAL, self::PHASE_ARRIVAL_DAY => $nights,
            self::PHASE_IN_STAY => max(0, $daysUntilCheckOut),
            self::PHASE_DEPARTURE_DAY, self::PHASE_POST_STAY => 0,
        };

        return [
            'now' => $nowLocal,
            'local_time' => $nowLocal->format('D H:i'),
            'timezone' => $tz,
            'phase' => $phase,
            'nights_total' => $nights,
            'total_days' => $totalDays,
            'is_day_use' => $nights === 0,
            'is_single_night' => $nights === 1,
            'day_of_stay' => $dayOfStay,
            'days_until_check_in' => $daysUntilCheckIn,
            'days_until_check_out' => $daysUntilCheckOut,
            'nights_remaining' => $nightsRemaining,
        ];
    }

    protected static function resolvePhase(Carbon $today, Carbon $checkIn, Carbon $checkOut): string
    {
        if ($today->lt($checkIn)) {
            return self::PHASE_PRE_ARRIVAL;
        }

        // arrival_day takes precedence over departure_day for day-use bookings
        // (check_in == check_out): the guest is arriving today, that's the more
        // useful framing for the agent.
        if ($today->eq($checkIn)) {
            return self::PHASE_ARRIVAL_DAY;
        }

        if ($today->eq($checkOut)) {
            return self::PHASE_DEPARTURE_DAY;
        }

        if ($today->gt($checkOut)) {
            return self::PHASE_POST_STAY;
        }

        return self::PHASE_IN_STAY;
    }

    /**
     * Render the context as a Markdown block for injection into an agent system prompt.
     */
    public static function format(array $context): string
    {
        $lines = [
            '## Current Status',
            "- Local time: {$context['local_time']} ({$context['timezone']})",
            "- Stay phase: {$context['phase']}",
        ];

        switch ($context['phase']) {
            case self::PHASE_PRE_ARRIVAL:
                $days = $context['days_until_check_in'];
                $lines[] = "- Check-in in {$days} day(s) — guest has not arrived yet";
                $lines[] = $context['is_day_use']
                    ? "- Day-use booking (no overnight stay)"
                    : "- Stay length: {$context['nights_total']} night(s)";
                break;

            case self::PHASE_ARRIVAL_DAY:
                if ($context['is_day_use']) {
                    $lines[] = "- Day-use booking — guest arrives AND departs today";
                } elseif ($context['is_single_night']) {
                    $lines[] = "- Single-night stay — guest arrives today, departs tomorrow";
                } else {
                    $lines[] = "- Arrival day (day 1 of {$context['total_days']}); {$context['nights_total']} night(s) ahead";
                }
                break;

            case self::PHASE_IN_STAY:
                $lines[] = "- Day {$context['day_of_stay']} of {$context['total_days']} — {$context['nights_remaining']} night(s) remaining";
                $lines[] = "- Check-out in {$context['days_until_check_out']} day(s)";
                break;

            case self::PHASE_DEPARTURE_DAY:
                $lines[] = $context['is_day_use']
                    ? "- Day-use booking ending today"
                    : "- Departure day — guest checks out today";
                break;

            case self::PHASE_POST_STAY:
                $daysSince = abs($context['days_until_check_out']);
                $lines[] = "- Guest checked out {$daysSince} day(s) ago";
                break;
        }

        return implode("\n", $lines);
    }
}
