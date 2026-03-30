<?php

use App\Ai\Agents\GuestReplyAgent;
use App\Ai\Agents\UpsellReplyAgent;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\UpsellLog;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['whatsapp.webhook_secret' => 'test-whatsapp-secret']);
    Http::fake([
        'api.p.2chat.io/*' => Http::response(['message_uuid' => 'fake-uuid'], 200),
    ]);
    // Fake AI agents to avoid real API calls
    GuestReplyAgent::fake(['Hello! Welcome to Riad Larbi Khalis. How can I help you?']);
    UpsellReplyAgent::fake([['classification' => 'accepted', 'reply_message' => 'Wonderful! Your hammam is booked.']]);
});

test('skips non-user messages', function () {
    $response = $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'agent',
        'message' => ['text' => 'Hello'],
    ], whatsappHeaders());

    $response->assertOk();
    expect(WhatsappMessage::count())->toBe(0);
});

test('skips system events', function () {
    $response = $this->postJson('/whatsapp/webhook', [
        'event' => 'disconnected',
        'channel_uuid' => 'WPN123',
    ], whatsappHeaders());

    $response->assertOk();
    expect(WhatsappMessage::count())->toBe(0);
});

test('skips reaction events', function () {
    $response = $this->postJson('/whatsapp/webhook', [
        'reaction' => '💯',
        'message' => ['id' => 'MSG123'],
    ], whatsappHeaders());

    $response->assertOk();
    expect(WhatsappMessage::count())->toBe(0);
});

test('stores inbound message and sends AI reply for active booking', function () {
    $booking = Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'checked_in',
    ]);

    $response = $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'uuid' => 'MSG-123',
        'message' => ['text' => 'What is the WiFi password?'],
        'contact' => ['first_name' => 'Pierre', 'last_name' => 'Dupont'],
    ], whatsappHeaders());

    $response->assertOk();

    // Inbound message stored
    expect(WhatsappMessage::where('direction', 'inbound')->count())->toBe(1);
    $inbound = WhatsappMessage::where('direction', 'inbound')->first();
    expect($inbound->booking_id)->toBe($booking->id);
    expect($inbound->message_body)->toBe('What is the WiFi password?');
    expect($inbound->twochat_message_id)->toBe('MSG-123');

    // Outbound AI reply stored
    expect(WhatsappMessage::where('direction', 'outbound')->count())->toBe(1);
    $outbound = WhatsappMessage::where('direction', 'outbound')->first();
    expect($outbound->agent_source)->toBe('guest_reply');
    expect($outbound->booking_id)->toBe($booking->id);

    // System log created
    expect(SystemLog::where('agent', 'guest_reply')->where('status', 'success')->count())->toBe(1);
});

test('sends fallback reply when no active booking found', function () {
    $response = $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33600000000',
        'sent_by' => 'user',
        'message' => ['text' => 'Hello'],
    ], whatsappHeaders());

    $response->assertOk();

    // Inbound stored without booking
    $inbound = WhatsappMessage::where('direction', 'inbound')->first();
    expect($inbound->booking_id)->toBeNull();

    // Fallback outbound stored
    $outbound = WhatsappMessage::where('direction', 'outbound')->first();
    expect($outbound->message_body)->toContain("don't have an active booking");
    expect($outbound->message_body)->toContain("réservation active");

    // Logged as skipped
    expect(SystemLog::where('action', 'skipped')->count())->toBe(1);
});

test('extracts text from media-only message', function () {
    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['media' => ['type' => 'image', 'mime_type' => 'image/jpeg']],
    ], whatsappHeaders());

    $inbound = WhatsappMessage::where('direction', 'inbound')->first();
    expect($inbound->message_body)->toBe('[Sent a image]');
});

test('extracts text from edited message', function () {
    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['old_text' => 'Helo', 'text' => 'Hello'],
    ], whatsappHeaders());

    $inbound = WhatsappMessage::where('direction', 'inbound')->first();
    expect($inbound->message_body)->toBe('Hello');
});

test('detects upsell reply and classifies as accepted', function () {
    $offer = Offer::factory()->create(['price' => 800.00, 'currency' => 'MAD']);

    $booking = Booking::factory()->checkedIn()->create([
        'guest_phone' => '+33612345678',
        'current_upsell_offer_id' => $offer->id,
        'upsell_offer_sent_at' => now()->subHours(2),
    ]);

    UpsellLog::factory()->create([
        'booking_id' => $booking->id,
        'offer_id' => $offer->id,
        'outcome' => 'pending',
        'guest_reply' => null,
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'Yes please, book it!'],
    ], whatsappHeaders());

    // Upsell log updated
    $log = UpsellLog::where('booking_id', $booking->id)->first();
    expect($log->outcome)->toBe('accepted');
    expect($log->guest_reply)->toBe('Yes please, book it!');
    expect($log->revenue_generated)->toBe('800.00');

    // Transaction created
    expect(Transaction::where('booking_id', $booking->id)->where('category', 'upsell')->count())->toBe(1);

    // Pending upsell cleared
    $booking->refresh();
    expect($booking->current_upsell_offer_id)->toBeNull();

    // System log for upsell_recv
    expect(SystemLog::where('agent', 'upsell_recv')->count())->toBe(1);
});

test('does not trigger upsell handler if offer sent more than 48h ago', function () {
    $offer = Offer::factory()->create();

    Booking::factory()->checkedIn()->create([
        'guest_phone' => '+33612345678',
        'current_upsell_offer_id' => $offer->id,
        'upsell_offer_sent_at' => now()->subHours(49),
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'Sure!'],
    ], whatsappHeaders());

    // Should go through guest_reply, not upsell_recv
    expect(SystemLog::where('agent', 'guest_reply')->count())->toBe(1);
    expect(SystemLog::where('agent', 'upsell_recv')->count())->toBe(0);
});

test('picks latest booking when multiple exist for same phone', function () {
    $old = Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'checked_out',
        'check_in' => '2026-01-01',
    ]);

    $current = Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'checked_in',
        'check_in' => '2026-07-10',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'Hello'],
    ], whatsappHeaders());

    $inbound = WhatsappMessage::where('direction', 'inbound')->first();
    expect($inbound->booking_id)->toBe($current->id);
});

test('rejects webhook when secret is not configured', function () {
    config(['whatsapp.webhook_secret' => null]);

    $response = $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'Hello'],
    ]);

    $response->assertStatus(403);
    expect(WhatsappMessage::count())->toBe(0);
});

/**
 * Return the WhatsApp webhook secret header.
 */
function whatsappHeaders(): array
{
    return ['X-Webhook-Secret' => config('whatsapp.webhook_secret')];
}
