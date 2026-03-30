<?php

use App\Models\Booking;
use App\Models\Offer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\UpsellLog;
use App\Models\WhatsappMessage;
use Carbon\Carbon;

test('currentDayOfStay returns null when not checked in', function () {
    $booking = Booking::factory()->create(['booking_status' => 'confirmed']);
    expect($booking->currentDayOfStay())->toBeNull();
});

test('currentDayOfStay returns 1 on check-in day', function () {
    $booking = Booking::factory()->checkedIn()->create([
        'check_in' => Carbon::today(),
    ]);

    expect($booking->currentDayOfStay())->toBe(1);
});

test('currentDayOfStay returns correct day mid-stay', function () {
    $booking = Booking::factory()->checkedIn()->create([
        'check_in' => Carbon::today()->subDays(2),
    ]);

    expect($booking->currentDayOfStay())->toBe(3);
});

test('booking has many upsell logs', function () {
    $booking = Booking::factory()->create();
    $offer = Offer::factory()->create();

    UpsellLog::factory()->count(3)->create([
        'booking_id' => $booking->id,
        'offer_id' => $offer->id,
    ]);

    expect($booking->upsellLogs)->toHaveCount(3);
});

test('booking has many whatsapp messages', function () {
    $booking = Booking::factory()->create();

    WhatsappMessage::create([
        'booking_id' => $booking->id,
        'direction' => 'inbound',
        'phone_number' => $booking->guest_phone,
        'message_body' => 'Hello',
        'received_at' => now(),
    ]);

    WhatsappMessage::create([
        'booking_id' => $booking->id,
        'direction' => 'outbound',
        'phone_number' => $booking->guest_phone,
        'message_body' => 'Hi there!',
        'agent_source' => 'guest_reply',
        'sent_at' => now(),
    ]);

    expect($booking->whatsappMessages)->toHaveCount(2);
});

test('booking has many transactions', function () {
    $booking = Booking::factory()->create();

    Transaction::create([
        'booking_id' => $booking->id,
        'type' => 'income',
        'category' => 'room_revenue',
        'description' => 'Room charge',
        'amount' => 5000,
        'transaction_date' => now()->toDateString(),
    ]);

    expect($booking->transactions)->toHaveCount(1);
});

test('booking has many system logs', function () {
    $booking = Booking::factory()->create();

    SystemLog::create([
        'agent' => 'guest_reply',
        'action' => 'reply_sent',
        'booking_id' => $booking->id,
        'status' => 'success',
    ]);

    expect($booking->systemLogs)->toHaveCount(1);
});

test('booking belongs to current offer', function () {
    $offer = Offer::factory()->create();
    $booking = Booking::factory()->create([
        'current_upsell_offer_id' => $offer->id,
    ]);

    expect($booking->currentOffer->id)->toBe($offer->id);
});

test('offer scope active filters correctly', function () {
    Offer::factory()->create(['is_active' => true]);
    Offer::factory()->create(['is_active' => true]);
    Offer::factory()->create(['is_active' => false]);

    expect(Offer::active()->count())->toBe(2);
});

test('upsell log belongs to booking and offer', function () {
    $booking = Booking::factory()->create();
    $offer = Offer::factory()->create();
    $log = UpsellLog::factory()->create([
        'booking_id' => $booking->id,
        'offer_id' => $offer->id,
    ]);

    expect($log->booking->id)->toBe($booking->id);
    expect($log->offer->id)->toBe($offer->id);
});

test('system log casts payload as array', function () {
    $log = SystemLog::create([
        'agent' => 'test',
        'action' => 'test_action',
        'status' => 'success',
        'payload' => ['key' => 'value', 'nested' => ['a' => 1]],
    ]);

    $log->refresh();
    expect($log->payload)->toBeArray();
    expect($log->payload['key'])->toBe('value');
    expect($log->payload['nested']['a'])->toBe(1);
});
