<?php

use App\Ai\Agents\GuestReplyAgent;
use App\Models\Booking;
use Carbon\Carbon;

test('returns null when conversation_state is preferences_complete', function () {
    $booking = Booking::factory()->create([
        'conversation_state' => 'preferences_complete',
        'pref_arrival_time' => null,
    ]);

    $agent = new GuestReplyAgent($booking);

    expect($agent->nextPreferenceToAsk())->toBeNull();
});

test('picks highest-priority missing preference (arrival_time) when none asked', function () {
    $booking = Booking::factory()->create([
        'conversation_state' => 'waiting_preferences',
        'pref_arrival_time' => null,
        'pref_bed_type' => null,
        'pref_airport_transfer' => null,
        'pref_special_requests' => null,
        'preferences_asked' => null,
    ]);

    $agent = new GuestReplyAgent($booking);

    expect($agent->nextPreferenceToAsk())->toBe('arrival_time');
});

test('skips a recently-asked preference and falls back to next priority', function () {
    $booking = Booking::factory()->create([
        'conversation_state' => 'waiting_preferences',
        'pref_arrival_time' => null,
        'pref_bed_type' => null,
        'pref_airport_transfer' => null,
        'pref_special_requests' => null,
        'preferences_asked' => [
            'arrival_time' => Carbon::now()->subHours(2)->toIso8601String(),
        ],
    ]);

    $agent = new GuestReplyAgent($booking);

    // arrival_time was asked 2h ago → on cooldown → next priority is airport_transfer
    expect($agent->nextPreferenceToAsk())->toBe('airport_transfer');
});

test('re-asks a preference once cooldown (24h) has elapsed', function () {
    $booking = Booking::factory()->create([
        'conversation_state' => 'waiting_preferences',
        'pref_arrival_time' => null,
        'pref_bed_type' => null,
        'pref_airport_transfer' => null,
        'pref_special_requests' => null,
        'preferences_asked' => [
            'arrival_time' => Carbon::now()->subHours(25)->toIso8601String(),
        ],
    ]);

    $agent = new GuestReplyAgent($booking);

    expect($agent->nextPreferenceToAsk())->toBe('arrival_time');
});

test('returns null when all missing prefs are on cooldown', function () {
    $now = Carbon::now();
    $booking = Booking::factory()->create([
        'conversation_state' => 'waiting_preferences',
        'pref_arrival_time' => null,
        'pref_bed_type' => null,
        'pref_airport_transfer' => null,
        'pref_special_requests' => null,
        'preferences_asked' => [
            'arrival_time' => $now->copy()->subHour()->toIso8601String(),
            'airport_transfer' => $now->copy()->subHour()->toIso8601String(),
            'bed_type' => $now->copy()->subHour()->toIso8601String(),
            'special_requests' => $now->copy()->subHour()->toIso8601String(),
        ],
    ]);

    $agent = new GuestReplyAgent($booking);

    expect($agent->nextPreferenceToAsk())->toBeNull();
});

test('skips already-collected preferences regardless of asked map', function () {
    $booking = Booking::factory()->create([
        'conversation_state' => 'preferences_partial',
        'pref_arrival_time' => '16:00',
        'pref_bed_type' => null,
        'pref_airport_transfer' => null,
        'pref_special_requests' => null,
        'preferences_asked' => null,
    ]);

    $agent = new GuestReplyAgent($booking);

    // arrival_time collected; next priority is airport_transfer
    expect($agent->nextPreferenceToAsk())->toBe('airport_transfer');
});

test('nextPreferenceToAsk is memoized and stable across calls', function () {
    $booking = Booking::factory()->create([
        'conversation_state' => 'waiting_preferences',
        'pref_arrival_time' => null,
        'pref_bed_type' => null,
        'pref_airport_transfer' => null,
        'pref_special_requests' => null,
    ]);

    $agent = new GuestReplyAgent($booking);
    $first = $agent->nextPreferenceToAsk();

    // Mutate the underlying model — memoized value should not change.
    $booking->pref_arrival_time = '15:00';

    expect($agent->nextPreferenceToAsk())->toBe($first);
});

test('1-night booking still gets preference asked on arrival day', function () {
    $today = Carbon::now()->toDateString();
    $tomorrow = Carbon::now()->addDay()->toDateString();

    $booking = Booking::factory()->create([
        'check_in' => $today,
        'check_out' => $tomorrow,
        'num_nights' => 1,
        'conversation_state' => 'waiting_preferences',
        'pref_arrival_time' => null,
        'pref_bed_type' => null,
        'pref_airport_transfer' => null,
        'pref_special_requests' => null,
    ]);

    $agent = new GuestReplyAgent($booking);

    expect($agent->nextPreferenceToAsk())->toBe('arrival_time');
});
