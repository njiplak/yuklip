<?php

use App\Ai\Agents\GuestReplyAgent;
use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['lodgify.webhook_secret' => 'test-webhook-secret']);
    Http::fake([
        'api.p.2chat.io/*' => Http::response(['message_uuid' => 'fake-uuid'], 200),
    ]);
    GuestReplyAgent::fake(['Welcome to Riad Larbi Khalis! We are delighted to have you.']);
});

test('creates new booking from lodgify webhook', function () {
    $payload = makeLodgifyBookingPayload(action: 'booking_new', lodgifyId: 99001);

    $response = $this->postJson('/lodgify/webhook', $payload, lodgifyHeaders($payload));

    $response->assertOk();

    $booking = Booking::where('lodgify_booking_id', '99001')->first();
    expect($booking)->not->toBeNull();
    expect($booking->guest_name)->toBe('John Doe');
    expect($booking->guest_phone)->toBe('+33612345678');
    expect($booking->booking_status)->toBe('confirmed');
    expect($booking->lodgify_synced_at)->not->toBeNull();

    // System log: booking_created
    expect(SystemLog::where('agent', 'lodgify_sync')->where('action', 'booking_created')->count())->toBe(1);
});

test('sends welcome message for new confirmed booking', function () {
    $payload = makeLodgifyBookingPayload(action: 'booking_new', lodgifyId: 99002);

    $this->postJson('/lodgify/webhook', $payload, lodgifyHeaders($payload));

    // Welcome outbound message stored
    $outbound = WhatsappMessage::where('direction', 'outbound')->where('agent_source', 'lodgify_sync')->first();
    expect($outbound)->not->toBeNull();
    expect($outbound->phone_number)->toBe('+33612345678');

    // Welcome log
    expect(SystemLog::where('action', 'welcome_sent')->count())->toBe(1);
});

test('updates existing booking on booking_change', function () {
    Booking::factory()->create([
        'lodgify_booking_id' => '99003',
        'guest_name' => 'Old Name',
    ]);

    $payload = makeLodgifyBookingPayload(action: 'booking_change', lodgifyId: 99003);

    $this->postJson('/lodgify/webhook', $payload, lodgifyHeaders($payload));

    $booking = Booking::where('lodgify_booking_id', '99003')->first();
    expect($booking->guest_name)->toBe('John Doe');

    // Should be booking_updated, not booking_created
    expect(SystemLog::where('action', 'booking_updated')->count())->toBe(1);
    expect(SystemLog::where('action', 'booking_created')->count())->toBe(0);
});

test('does not send welcome message on update', function () {
    Booking::factory()->create(['lodgify_booking_id' => '99004']);

    $payload = makeLodgifyBookingPayload(action: 'booking_change', lodgifyId: 99004);

    $this->postJson('/lodgify/webhook', $payload, lodgifyHeaders($payload));

    expect(WhatsappMessage::where('agent_source', 'lodgify_sync')->count())->toBe(0);
});

test('handles booking cancellation and schedules recovery job', function () {
    Booking::factory()->create([
        'lodgify_booking_id' => '99005',
        'booking_status' => 'confirmed',
    ]);

    $payload = makeLodgifyBookingPayload(action: 'booking_cancelled', lodgifyId: 99005);

    $this->postJson('/lodgify/webhook', $payload, lodgifyHeaders($payload));

    $booking = Booking::where('lodgify_booking_id', '99005')->first();
    expect($booking->booking_status)->toBe('cancelled');

    // Recovery scheduled
    expect(SystemLog::where('agent', 'cancellation_recovery')->where('action', 'recovery_scheduled')->count())->toBe(1);
});

test('handles booking_deleted by setting status to cancelled', function () {
    Booking::factory()->create([
        'lodgify_booking_id' => '99006',
        'booking_status' => 'confirmed',
    ]);

    $payload = [
        'action' => 'booking_deleted',
        'booking' => ['id' => 99006],
        'is_deleted' => true,
    ];

    $this->postJson('/lodgify/webhook', $payload, lodgifyHeaders($payload));

    $booking = Booking::where('lodgify_booking_id', '99006')->first();
    expect($booking->booking_status)->toBe('cancelled');
});

test('does not overwrite checked_in status on booking_change', function () {
    Booking::factory()->create([
        'lodgify_booking_id' => '99007',
        'booking_status' => 'checked_in',
    ]);

    $payload = makeLodgifyBookingPayload(action: 'booking_change', lodgifyId: 99007);

    $this->postJson('/lodgify/webhook', $payload, lodgifyHeaders($payload));

    $booking = Booking::where('lodgify_booking_id', '99007')->first();
    expect($booking->booking_status)->toBe('checked_in');
    // Guest name should still be updated from Lodgify
    expect($booking->guest_name)->toBe('John Doe');
});

test('maps lodgify statuses correctly', function () {
    foreach ([
        ['Booked', 'confirmed'],
        ['Confirmed', 'confirmed'],
        ['Open', 'confirmed'],
        ['Declined', 'cancelled'],
        ['Cancelled', 'cancelled'],
    ] as [$lodgifyStatus, $expected]) {
        $payload = makeLodgifyBookingPayload(
            action: 'booking_new',
            lodgifyId: random_int(100000, 999999),
            status: $lodgifyStatus,
        );

        $this->postJson('/lodgify/webhook', $payload, lodgifyHeaders($payload));

        $booking = Booking::where('lodgify_booking_id', (string) $payload['booking']['id'])->first();
        expect($booking->booking_status)->toBe($expected, "Lodgify status '{$lodgifyStatus}' should map to '{$expected}'");
    }
});

test('rejects webhook with invalid signature when secret is configured', function () {
    config(['lodgify.webhook_secret' => 'test-secret']);

    $response = $this->postJson('/lodgify/webhook', [
        'action' => 'rate_change',
        'property_id' => 1000,
    ], ['ms-signature' => 'sha256=invalid']);

    $response->assertStatus(401);
});

test('rejects webhook when secret is not configured', function () {
    config(['lodgify.webhook_secret' => null]);

    $response = $this->postJson('/lodgify/webhook', [
        'action' => 'rate_change',
        'property_id' => 1000,
    ]);

    $response->assertStatus(401);
});

test('logs rate_change and availability_change without error', function () {
    $ratePayload = [
        'action' => 'rate_change',
        'property_id' => 1000,
        'room_type_ids' => [123],
    ];
    $this->postJson('/lodgify/webhook', $ratePayload, lodgifyHeaders($ratePayload))->assertOk();

    $availPayload = [
        'action' => 'availability_change',
        'property_id' => 1000,
        'room_type_ids' => [123],
        'start' => '2026-07-01',
        'end' => '2026-07-05',
        'source' => 'Manual',
    ];
    $this->postJson('/lodgify/webhook', $availPayload, lodgifyHeaders($availPayload))->assertOk();
});

/**
 * Compute Lodgify webhook HMAC signature headers for a payload.
 */
function lodgifyHeaders(array $payload): array
{
    $body = json_encode($payload);
    $secret = config('lodgify.webhook_secret');

    return ['ms-signature' => 'sha256=' . hash_hmac('sha256', $body, $secret)];
}

/**
 * Helper to build a realistic Lodgify booking webhook payload.
 */
function makeLodgifyBookingPayload(string $action, int $lodgifyId, string $status = 'Booked'): array
{
    return [
        'action' => $action,
        'booking' => [
            'id' => $lodgifyId,
            'type' => 'Booking',
            'date_arrival' => '2026-07-20T00:00:00',
            'date_departure' => '2026-07-24T00:00:00',
            'property_id' => 10000,
            'property_name' => 'Riad Larbi Khalis',
            'status' => $status,
            'room_types' => [
                ['id' => 1, 'room_type_id' => 100, 'name' => 'Suite Al Andalus', 'people' => 2],
            ],
            'source' => 'Direct',
            'source_text' => 'Direct',
            'nights' => 4,
        ],
        'guest' => [
            'uid' => 'GUEST-123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone_number' => '+33612345678',
            'country' => 'French',
        ],
        'booking_total_amount' => '7600',
        'booking_currency_code' => 'MAD',
    ];
}
