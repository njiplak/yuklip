<?php

use App\Ai\Agents\GuestReplyAgent;
use App\Ai\Agents\PreferenceExtractorAgent;
use App\Ai\Agents\ServiceRequestDetectorAgent;
use App\Ai\Agents\UpsellReplyAgent;
use App\Models\Booking;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\UpsellLog;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.p.2chat.io/*' => Http::sequence()
            ->push(['message_uuid' => 'fake-uuid-1'], 200)
            ->push(['message_uuid' => 'fake-uuid-2'], 200)
            ->push(['message_uuid' => 'fake-uuid-3'], 200)
            ->push(['message_uuid' => 'fake-uuid-4'], 200)
            ->push(['message_uuid' => 'fake-uuid-5'], 200),
        'api.p.p.2chat.io/*' => Http::sequence()
            ->push(['message_uuid' => 'fake-uuid-1'], 200)
            ->push(['message_uuid' => 'fake-uuid-2'], 200)
            ->push(['message_uuid' => 'fake-uuid-3'], 200)
            ->push(['message_uuid' => 'fake-uuid-4'], 200)
            ->push(['message_uuid' => 'fake-uuid-5'], 200),
    ]);
    // Fake AI agents to avoid real API calls
    GuestReplyAgent::fake(['Hello! Welcome to Riad Larbi Khalis. How can I help you?']);
    UpsellReplyAgent::fake([['classification' => 'accepted', 'reply_message' => 'Wonderful! Your hammam is booked.']]);
    PreferenceExtractorAgent::fake([['arrival_time' => null, 'bed_type' => null, 'airport_transfer' => null, 'special_requests' => null]]);
});

test('rejects webhook with wrong User-Agent', function () {
    $response = $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'Hello'],
    ], ['User-Agent' => 'curl/8.0']);

    $response->assertStatus(403);
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
        'conversation_state' => 'preferences_complete',
    ]);

    $response = $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'uuid' => 'MSG-123',
        'message' => ['text' => 'What is the WiFi password?'],
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

test('handles non-text messages with polite rejection', function () {
    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
        'conversation_state' => 'waiting_preferences',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['media' => ['type' => 'image', 'mime_type' => 'image/jpeg']],
    ], whatsappHeaders());

    // Inbound message stored as media placeholder
    $inbound = WhatsappMessage::where('direction', 'inbound')->first();
    expect($inbound->message_body)->toBe('[Sent a image]');

    // Polite text-only reply sent
    $outbound = WhatsappMessage::where('direction', 'outbound')->first();
    expect($outbound->message_body)->toContain('text messages');

    // Logged as non-text
    expect(SystemLog::where('action', 'non_text_received')->count())->toBe(1);
});

test('bot stays silent when booking is in handover_human state', function () {
    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
        'conversation_state' => 'handover_human',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'Hello?'],
    ], whatsappHeaders());

    // Inbound stored
    expect(WhatsappMessage::where('direction', 'inbound')->count())->toBe(1);

    // No outbound reply (bot is silent)
    expect(WhatsappMessage::where('direction', 'outbound')->count())->toBe(0);

    // Logged as skipped
    $log = SystemLog::where('action', 'skipped')->first();
    expect($log->payload['reason'])->toBe('state_handover_human');
});

test('bot stays silent when booking is cancelled', function () {
    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'cancelled',
        'conversation_state' => 'cancelled',
    ]);

    // Cancelled bookings are not returned by the phone lookup (only confirmed/checked_in),
    // so the guest gets a fallback reply instead
    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'Hello?'],
    ], whatsappHeaders());

    // Should get fallback (no active booking)
    $outbound = WhatsappMessage::where('direction', 'outbound')->first();
    expect($outbound->message_body)->toContain("don't have an active booking");
});

test('resets follow-up count when guest replies', function () {
    $booking = Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
        'conversation_state' => 'waiting_preferences',
        'follow_up_count' => 1,
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'I arrive at 3pm'],
    ], whatsappHeaders());

    $booking->refresh();
    expect($booking->follow_up_count)->toBe(0);
});

test('extracts text from edited message', function () {
    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
        'conversation_state' => 'preferences_complete',
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
        'conversation_state' => 'preferences_complete',
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
        'conversation_state' => 'preferences_complete',
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
    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'checked_out',
        'check_in' => '2026-01-01',
    ]);

    $current = Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'checked_in',
        'check_in' => '2026-07-10',
        'conversation_state' => 'preferences_complete',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'message' => ['text' => 'Hello'],
    ], whatsappHeaders());

    $inbound = WhatsappMessage::where('direction', 'inbound')->first();
    expect($inbound->booking_id)->toBe($current->id);
});

test('deduplicates messages by UUID', function () {
    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
        'conversation_state' => 'preferences_complete',
    ]);

    $payload = [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'uuid' => 'MSG-DUPLICATE-123',
        'message' => ['text' => 'Hello'],
    ];

    // First request
    $this->postJson('/whatsapp/webhook', $payload, whatsappHeaders());
    expect(WhatsappMessage::where('direction', 'inbound')->count())->toBe(1);

    // Second request with same UUID
    $this->postJson('/whatsapp/webhook', $payload, whatsappHeaders());
    expect(WhatsappMessage::where('direction', 'inbound')->count())->toBe(1);
});

// ─── Fix A: Preference context persists after collection ───

test('GuestReplyAgent includes collected preferences in instructions when preferences_complete', function () {
    $booking = Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
        'conversation_state' => 'preferences_complete',
        'pref_arrival_time' => 'around 3pm',
        'pref_bed_type' => 'twin',
        'pref_airport_transfer' => 'yes',
        'pref_special_requests' => 'no alcohol, no pork, halal only, flight FL-1000, ETA 1:00 pm',
    ]);

    $agent = new GuestReplyAgent($booking);
    $instructions = $agent->instructions();

    expect($instructions)->toContain('DO NOT RE-ASK');
    expect($instructions)->toContain('around 3pm');
    expect($instructions)->toContain('twin');
    expect($instructions)->toContain('yes');
    expect($instructions)->toContain('flight FL-1000');
    expect($instructions)->toContain($booking->check_in->format('D, M d, Y'));
});

test('GuestReplyAgent shows active collection instructions when preferences are partial', function () {
    $booking = Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'confirmed',
        'conversation_state' => 'preferences_partial',
        'pref_arrival_time' => 'around 3pm',
        'pref_bed_type' => null,
        'pref_airport_transfer' => null,
        'pref_special_requests' => null,
    ]);

    $agent = new GuestReplyAgent($booking);
    $instructions = $agent->instructions();

    expect($instructions)->toContain('Preference Collection (ACTIVE)');
    expect($instructions)->toContain('Already collected: Arrival time: around 3pm');
    expect($instructions)->toContain('bed preference');
});

// ─── Fix B: Service request detection and staff notification ───

test('notifies staff when service request is detected', function () {
    config(['whatsapp.staff_phone_number' => '+212661234567']);

    ServiceRequestDetectorAgent::fake([[
        'requires_staff_action' => true,
        'request_summary' => 'Guest wants fresh melon juice',
        'urgency' => 'normal',
    ]]);

    $booking = Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'checked_in',
        'conversation_state' => 'preferences_complete',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'uuid' => 'MSG-MELON-1',
        'message' => ['text' => 'I want melon juice'],
    ], whatsappHeaders());

    // Staff notification sent
    $staffMessage = WhatsappMessage::where('agent_source', 'service_request')->first();
    expect($staffMessage)->not->toBeNull();
    expect($staffMessage->phone_number)->toBe('+212661234567');
    expect($staffMessage->message_body)->toContain('SERVICE REQUEST');
    expect($staffMessage->message_body)->toContain('Guest wants fresh melon juice');
    expect($staffMessage->message_body)->toContain('I want melon juice');

    // System log created
    $log = SystemLog::where('agent', 'service_request')->first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe('staff_notified');
    expect($log->payload['summary'])->toBe('Guest wants fresh melon juice');
});

test('does not notify staff when no service request detected', function () {
    config(['whatsapp.staff_phone_number' => '+212661234567']);

    ServiceRequestDetectorAgent::fake([[
        'requires_staff_action' => false,
        'request_summary' => null,
        'urgency' => 'normal',
    ]]);

    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'checked_in',
        'conversation_state' => 'preferences_complete',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'uuid' => 'MSG-WIFI-1',
        'message' => ['text' => 'What is the wifi password?'],
    ], whatsappHeaders());

    expect(WhatsappMessage::where('agent_source', 'service_request')->count())->toBe(0);
    expect(SystemLog::where('agent', 'service_request')->count())->toBe(0);
});

test('urgent service request includes URGENT label in staff notification', function () {
    config(['whatsapp.staff_phone_number' => '+212661234567']);

    ServiceRequestDetectorAgent::fake([[
        'requires_staff_action' => true,
        'request_summary' => 'AC not working in guest room',
        'urgency' => 'urgent',
    ]]);

    Booking::factory()->create([
        'guest_phone' => '+33612345678',
        'booking_status' => 'checked_in',
        'conversation_state' => 'preferences_complete',
    ]);

    $this->postJson('/whatsapp/webhook', [
        'remote_phone_number' => '+33612345678',
        'sent_by' => 'user',
        'uuid' => 'MSG-AC-1',
        'message' => ['text' => 'The AC is broken, it is very hot'],
    ], whatsappHeaders());

    $staffMessage = WhatsappMessage::where('agent_source', 'service_request')->first();
    expect($staffMessage->message_body)->toContain('URGENT SERVICE REQUEST');
});

// ─── Fix C: Menu items in AI context ───

test('GuestReplyAgent includes available menu items in instructions', function () {
    MenuItem::factory()->create(['name' => 'Moroccan Mint Tea', 'category' => 'drinks', 'price' => null, 'is_available' => true]);
    MenuItem::factory()->create(['name' => 'Fresh Melon Juice', 'category' => 'drinks', 'price' => 45.00, 'is_available' => true]);
    MenuItem::factory()->create(['name' => 'Expired Item', 'category' => 'drinks', 'price' => 30.00, 'is_available' => false]);

    $booking = Booking::factory()->create([
        'conversation_state' => 'preferences_complete',
    ]);

    $agent = new GuestReplyAgent($booking);
    $instructions = $agent->instructions();

    expect($instructions)->toContain('Available Menu & Drinks');
    expect($instructions)->toContain('Moroccan Mint Tea');
    expect($instructions)->toContain('Fresh Melon Juice');
    expect($instructions)->toContain('45.00 MAD');
    expect($instructions)->not->toContain('Expired Item');
});

test('GuestReplyAgent excludes menu section when no items exist', function () {
    $booking = Booking::factory()->create([
        'conversation_state' => 'preferences_complete',
    ]);

    $agent = new GuestReplyAgent($booking);
    $instructions = $agent->instructions();

    expect($instructions)->not->toContain('Available Menu & Drinks');
});

/**
 * Return the WhatsApp webhook headers (User-Agent based auth).
 */
function whatsappHeaders(): array
{
    return ['User-Agent' => '2Chat'];
}
