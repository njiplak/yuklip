<?php

use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

test('returns escalations when there are no cancellations', function () {
    // Regression: an empty cancellations Eloquent\Collection used to keep its
    // type, so merge() called getKey() on the escalation arrays and fatalled.
    SystemLog::create([
        'agent' => 'whatsapp',
        'action' => 'escalated_to_manager',
        'booking_id' => null,
        'payload' => ['reason' => 'Guest not responding'],
        'status' => 'success',
    ]);

    $response = $this->getJson('/api/alerts');

    $response->assertOk()
        ->assertJsonPath('message', 'Alerts retrieved.')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'escalation')
        ->assertJsonPath('data.0.details', 'Guest not responding');
});

test('merges cancellations and escalations sorted by recency', function () {
    $booking = Booking::factory()->create([
        'booking_status' => 'cancelled',
        'updated_at' => now()->subDay(),
    ]);

    SystemLog::create([
        'agent' => 'whatsapp',
        'action' => 'sentiment_escalated',
        'booking_id' => $booking->id,
        'payload' => ['sentiment' => 'issue_detected'],
        'status' => 'success',
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/alerts');

    $response->assertOk()->assertJsonCount(2, 'data');
    // Escalation (now) sorts before cancellation (yesterday).
    expect($response->json('data.0.type'))->toBe('escalation');
    expect($response->json('data.1.type'))->toBe('cancellation');
    expect($response->json('data.1.id'))->toBe('cancel-' . $booking->id);
});

test('returns an empty list when there are no alerts', function () {
    $response = $this->getJson('/api/alerts');

    $response->assertOk()->assertJsonCount(0, 'data');
});

test('requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/alerts')->assertUnauthorized();
});
