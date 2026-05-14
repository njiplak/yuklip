<?php

use App\Models\Booking;
use App\Service\Booking\StayContext;
use Carbon\Carbon;

function makeBooking(string $checkIn, string $checkOut): Booking
{
    return new Booking([
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'num_nights' => Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)),
    ]);
}

test('pre_arrival when today is before check-in', function () {
    $booking = makeBooking('2026-06-10', '2026-06-13');
    $now = Carbon::parse('2026-06-08 10:00:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');

    expect($ctx['phase'])->toBe(StayContext::PHASE_PRE_ARRIVAL);
    expect($ctx['days_until_check_in'])->toBe(2);
    expect($ctx['days_until_check_out'])->toBe(5);
    expect($ctx['nights_total'])->toBe(3);
    expect($ctx['nights_remaining'])->toBe(3);
    expect($ctx['day_of_stay'])->toBeNull();
});

test('arrival_day when today is the check-in date — multi-night', function () {
    $booking = makeBooking('2026-06-10', '2026-06-13');
    $now = Carbon::parse('2026-06-10 14:00:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');

    expect($ctx['phase'])->toBe(StayContext::PHASE_ARRIVAL_DAY);
    expect($ctx['day_of_stay'])->toBe(1);
    expect($ctx['total_days'])->toBe(4);
    expect($ctx['nights_remaining'])->toBe(3);
    expect($ctx['is_single_night'])->toBeFalse();
    expect($ctx['is_day_use'])->toBeFalse();
});

test('arrival_day for a 1-night booking on arrival date', function () {
    $booking = makeBooking('2026-06-10', '2026-06-11');
    $now = Carbon::parse('2026-06-10 15:00:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');

    expect($ctx['phase'])->toBe(StayContext::PHASE_ARRIVAL_DAY);
    expect($ctx['nights_total'])->toBe(1);
    expect($ctx['is_single_night'])->toBeTrue();
    expect($ctx['total_days'])->toBe(2);
    expect($ctx['day_of_stay'])->toBe(1);

    $rendered = StayContext::format($ctx);
    expect($rendered)->toContain('Single-night stay');
});

test('departure_day for a 1-night booking on check-out date', function () {
    $booking = makeBooking('2026-06-10', '2026-06-11');
    $now = Carbon::parse('2026-06-11 09:00:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');

    expect($ctx['phase'])->toBe(StayContext::PHASE_DEPARTURE_DAY);
    expect($ctx['day_of_stay'])->toBe(2);
    expect($ctx['nights_remaining'])->toBe(0);
});

test('1-night booking has no in_stay phase', function () {
    $booking = makeBooking('2026-06-10', '2026-06-11');
    // Walk the calendar — only PRE/ARRIVAL/DEPARTURE/POST should appear.
    $seen = [];
    foreach (['2026-06-09', '2026-06-10', '2026-06-11', '2026-06-12'] as $date) {
        $ctx = StayContext::compute(
            $booking,
            Carbon::parse("{$date} 10:00:00", 'Africa/Casablanca'),
            'Africa/Casablanca'
        );
        $seen[$date] = $ctx['phase'];
    }

    expect($seen)->toBe([
        '2026-06-09' => StayContext::PHASE_PRE_ARRIVAL,
        '2026-06-10' => StayContext::PHASE_ARRIVAL_DAY,
        '2026-06-11' => StayContext::PHASE_DEPARTURE_DAY,
        '2026-06-12' => StayContext::PHASE_POST_STAY,
    ]);
});

test('day-use booking (check_in == check_out) treated as arrival_day, not departure', function () {
    $booking = makeBooking('2026-06-10', '2026-06-10');
    $now = Carbon::parse('2026-06-10 12:00:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');

    expect($ctx['phase'])->toBe(StayContext::PHASE_ARRIVAL_DAY);
    expect($ctx['is_day_use'])->toBeTrue();
    expect($ctx['nights_total'])->toBe(0);
    expect($ctx['total_days'])->toBe(1);

    $rendered = StayContext::format($ctx);
    expect($rendered)->toContain('Day-use booking');
});

test('in_stay between arrival and departure for multi-night booking', function () {
    $booking = makeBooking('2026-06-10', '2026-06-14');
    $now = Carbon::parse('2026-06-12 18:30:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');

    expect($ctx['phase'])->toBe(StayContext::PHASE_IN_STAY);
    expect($ctx['day_of_stay'])->toBe(3);  // arrival = day 1, today = day 3
    expect($ctx['total_days'])->toBe(5);
    expect($ctx['nights_remaining'])->toBe(2);
    expect($ctx['days_until_check_out'])->toBe(2);
});

test('post_stay when today is after check-out', function () {
    $booking = makeBooking('2026-06-10', '2026-06-13');
    $now = Carbon::parse('2026-06-16 08:00:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');

    expect($ctx['phase'])->toBe(StayContext::PHASE_POST_STAY);
    expect($ctx['days_until_check_out'])->toBe(-3);
    expect($ctx['nights_remaining'])->toBe(0);

    $rendered = StayContext::format($ctx);
    expect($rendered)->toContain('checked out 3 day(s) ago');
});

test('inverted dates (check_out before check_in) degrade safely to 0-night', function () {
    $booking = makeBooking('2026-06-15', '2026-06-10');
    $now = Carbon::parse('2026-06-15 10:00:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');

    expect($ctx['nights_total'])->toBe(0);
    expect($ctx['is_day_use'])->toBeTrue();
    expect($ctx['phase'])->toBe(StayContext::PHASE_ARRIVAL_DAY);
});

test('timezone respected — same UTC moment differs across zones', function () {
    $booking = makeBooking('2026-06-10', '2026-06-13');
    // 23:30 UTC on June 9 = 00:30 on June 10 in Casablanca (UTC+1 in summer).
    $utcNow = Carbon::parse('2026-06-09 23:30:00', 'UTC');

    $ctxLocal = StayContext::compute($booking, $utcNow, 'Africa/Casablanca');
    $ctxUtc = StayContext::compute($booking, $utcNow, 'UTC');

    expect($ctxLocal['phase'])->toBe(StayContext::PHASE_ARRIVAL_DAY);
    expect($ctxUtc['phase'])->toBe(StayContext::PHASE_PRE_ARRIVAL);
});

test('format renders local time + phase header', function () {
    $booking = makeBooking('2026-06-10', '2026-06-13');
    $now = Carbon::parse('2026-06-11 18:30:00', 'Africa/Casablanca');

    $ctx = StayContext::compute($booking, $now, 'Africa/Casablanca');
    $rendered = StayContext::format($ctx);

    expect($rendered)->toContain('## Current Status');
    expect($rendered)->toContain('Africa/Casablanca');
    expect($rendered)->toContain('Stay phase: in_stay');
});
