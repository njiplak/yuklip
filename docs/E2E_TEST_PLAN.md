# E2E Test Plan - Riad Larbi Khalis Concierge

**Property:** Riad Larbi Khalis (4 suites)
**System:** E-Conciergerie (Laravel + Lodgify + 2Chat + Claude AI)
**Date:** March 31, 2026

---

## Prerequisites

Before testing, confirm all services are operational:

```bash
# 1. Verify environment
php artisan concierge:setup-webhooks
# All items should show [set], all webhooks [OK]

# 2. Verify queue worker is running
php artisan queue:work --queue=agents
# Keep this running in a separate terminal during all tests

# 3. Verify database is migrated
php artisan migrate:status
```

**Required accounts:**
- Lodgify test property with ability to create/edit/cancel bookings
- 2Chat connected WhatsApp number
- Test phone number that can receive WhatsApp messages
- Anthropic API key with billing enabled

**Date conventions for test bookings:**

| Test | Check-in | Check-out | Why |
|------|----------|-----------|-----|
| Test 1-4 (basic flow) | Tomorrow | Day after tomorrow (1 night) | Future booking triggers welcome message |
| Test 5 (deletion) | Tomorrow | Day after tomorrow | Same as above |
| Test 6-8 (upsell) | Today | 3 days from today | Must be `checked_in` with today as check-in for upsell timing to match |
| Test 9 (staff briefing) | Today (arrival) | Today (departure, separate booking) | Briefing reports today's arrivals and departures |

Use **tomorrow** for new booking tests (Tests 1-5) so the booking is in the future and triggers the welcome flow.
Use **today** for upsell and briefing tests (Tests 6-9) because those features operate on guests who are currently staying.

---

## Test 1: New Booking -> Welcome Message

**Goal:** Verify that creating a booking in Lodgify triggers a personalized WhatsApp welcome message.

### Steps

1. Open Lodgify dashboard
2. Create a new booking on the test property:
   - Guest name: Test Guest
   - Phone: your test phone number (E.164 format, e.g. +6281217141850)
   - Email: test@example.com
   - Check-in: **tomorrow** (so the booking is upcoming, not in the past)
   - Check-out: **day after tomorrow** (1-night stay)
   - Status: Booked/Open
3. Wait up to 60 seconds for the webhook to fire

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Webhook received | Backoffice > Webhook Logs | New entry with source=lodgify, status_code empty or 200 |
| Booking synced | Backoffice > Bookings | New booking with guest name, phone, dates matching Lodgify |
| System log | Backoffice > System Logs | Entry: agent=lodgify_sync, action=booking_created, status=success |
| Welcome sent | Check test phone WhatsApp | Personalized welcome message from the concierge |
| Message logged | DB: `whatsapp_messages` | direction=outbound, agent_source=lodgify_sync |
| Welcome log | Backoffice > System Logs | Entry: agent=lodgify_sync, action=welcome_sent, status=success |

### Verify via CLI

```bash
# Check booking was created
php artisan tinker --execute="print_r(App\Models\Booking::latest()->first()?->only(['guest_name','guest_phone','booking_status','check_in','check_out']))"

# Check system logs
php artisan tinker --execute="App\Models\SystemLog::latest()->take(3)->get(['agent','action','status','created_at'])->each(fn(\$l) => print(\$l->agent.' | '.\$l->action.' | '.\$l->status.' | '.\$l->created_at.PHP_EOL))"

# Check WhatsApp message was logged
php artisan tinker --execute="print_r(App\Models\WhatsappMessage::latest()->first()?->only(['direction','phone_number','agent_source','sent_at']))"
```

### Pass Criteria
- Booking exists in database with status=confirmed
- Guest received WhatsApp welcome message within 60 seconds
- All system logs show status=success

---

## Test 2: Booking Update (No Duplicate Welcome)

**Goal:** Verify that updating an existing booking syncs changes but does NOT send a second welcome message.

### Steps

1. In Lodgify, edit the booking created in Test 1:
   - Change check-out date to 2 days later
   - OR change guest count
2. Wait up to 60 seconds for the webhook

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Booking updated | Backoffice > Bookings | Dates or guest count updated |
| System log | Backoffice > System Logs | agent=lodgify_sync, action=booking_updated (NOT booking_created) |
| No duplicate welcome | Check test phone WhatsApp | NO second welcome message received |
| Single booking | Backoffice > Bookings | Only ONE booking for this Lodgify ID, not two |

### Verify via CLI

```bash
# Confirm only one booking exists for this Lodgify ID
php artisan tinker --execute="\$b = App\Models\Booking::latest()->first(); echo 'Updated: '.\$b->updated_at.' Check-out: '.\$b->check_out.PHP_EOL"

# Confirm action is booking_updated
php artisan tinker --execute="echo App\Models\SystemLog::latest()->first()?->action"
```

### Pass Criteria
- Booking record updated (not duplicated)
- System log shows `booking_updated`
- No second WhatsApp welcome message

---

## Test 3: Guest Reply -> AI Concierge Response

**Goal:** Verify that when a guest replies on WhatsApp, the AI concierge responds with a context-aware answer.

### Steps

1. From the test phone, reply to the welcome message with: **"What time is check-in?"**
2. Wait up to 30 seconds for the AI response

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Inbound logged | DB: `whatsapp_messages` | direction=inbound, phone matches test phone |
| AI replied | Check test phone WhatsApp | Context-aware reply about check-in |
| Outbound logged | DB: `whatsapp_messages` | direction=outbound, agent_source=guest_reply |
| System log | Backoffice > System Logs | agent=guest_reply, action=reply_sent, status=success |
| Duration tracked | Backoffice > System Logs | duration_ms field populated |

### Additional Test Messages

Try these to verify the AI has booking context:

| Send | Expected AI Behavior |
|------|---------------------|
| "What is the wifi password?" | Answers with property wifi details |
| "Can you recommend a restaurant nearby?" | Gives local recommendations |
| "How many nights am I staying?" | References the correct booking dates |
| "Thank you!" | Polite acknowledgment, offers further help |

---

## Test 3a: Preference Collection Flow

**Goal:** Verify that the concierge collects 4 guest preferences through natural conversation, then sends a staff briefing when complete.

### Steps

1. Complete Test 1 (new booking with welcome message)
2. The welcome message should ask about arrival time
3. Reply with: **"I'll arrive around 3pm"**
4. Wait for AI response — it should acknowledge and ask about bed preference
5. Reply with: **"Double bed please"**
6. Wait for AI response — it should ask about airport transfer
7. Reply with: **"No thanks, we have a car"**
8. Wait for AI response — it should ask about special requests
9. Reply with: **"No special requests"**
10. Wait — the staff WhatsApp number should receive a preparation briefing

### Expected Results

**After each reply:**

| Reply | Preference Extracted | Conversation State |
|-------|---------------------|-------------------|
| "I'll arrive around 3pm" | pref_arrival_time = "3pm" or "15:00" | preferences_partial |
| "Double bed please" | pref_bed_type = "double" | preferences_partial |
| "No thanks, we have a car" | pref_airport_transfer = "no" | preferences_partial |
| "No special requests" | pref_special_requests = "none" | preferences_complete |

**After all 4 collected:**

| Check | How to Verify | Expected |
|-------|--------------|----------|
| State complete | Backoffice > Bookings > detail | conversation_state = preferences_complete |
| All preferences | Backoffice > Bookings > detail | All 4 preference fields populated |
| Staff briefing | Staff WhatsApp number | French briefing with guest preferences (arrival, bed, transfer, special requests) |
| System logs | Backoffice > System Logs | Multiple preferences_extracted entries + briefing_sent |

### Verify via CLI

```bash
# Check preference state
php artisan tinker --execute="\$b = App\Models\Booking::latest()->first(); echo 'State: '.\$b->conversation_state.PHP_EOL.'Arrival: '.\$b->pref_arrival_time.PHP_EOL.'Bed: '.\$b->pref_bed_type.PHP_EOL.'Transfer: '.\$b->pref_airport_transfer.PHP_EOL.'Special: '.\$b->pref_special_requests"

# Check extraction logs
php artisan tinker --execute="App\Models\SystemLog::where('agent','preference_extractor')->latest()->take(5)->get(['action','status','payload','created_at'])->each(fn(\$l) => print(\$l->action.' | '.\$l->status.' | '.json_encode(\$l->payload).PHP_EOL))"
```

### Alternative: All Preferences in One Message

Test that the system can extract multiple preferences from a single reply:

1. After welcome, reply: **"We arrive at 4pm, double bed, no airport transfer needed, and my wife is allergic to nuts"**
2. Verify: all 4 preferences extracted in one go, state = preferences_complete, staff briefing sent

### Pass Criteria
- Each preference extracted correctly from natural language
- Conversation state transitions: waiting_preferences -> preferences_partial -> preferences_complete
- AI asks for next missing preference naturally (not all at once)
- Staff briefing sent automatically when all 4 collected
- Works in any language (test with French: "On arrive vers 15h, lit double, pas de transfert, rien de spécial")

### Verify via CLI

```bash
# Check message pair (inbound + outbound)
php artisan tinker --execute="App\Models\WhatsappMessage::latest()->take(2)->get(['direction','agent_source','message_body','created_at'])->each(fn(\$m) => print(\$m->direction.' | '.\$m->agent_source.' | '.substr(\$m->message_body,0,60).'...'.PHP_EOL))"
```

### Pass Criteria
- Every guest message gets an AI response
- Responses are contextual (know the guest name, dates, suite)
- All messages logged in both directions

---

## Test 4: Booking Cancellation -> Recovery Message

**Goal:** Verify that cancelling a booking marks it as cancelled and sends a recovery message after 30 minutes.

### Steps

1. In Lodgify, cancel the booking from Test 1
2. Immediately check the system logs
3. Wait 30 minutes for the recovery message

### Expected Results

**Immediately after cancellation:**

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Status updated | Backoffice > Bookings | booking_status = cancelled |
| Cancellation logged | Backoffice > System Logs | agent=lodgify_sync, action=booking_cancelled |
| Recovery scheduled | Backoffice > System Logs | agent=cancellation_recovery, action=recovery_scheduled |

**After 30 minutes (requires queue worker running):**

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Recovery sent | Check test phone WhatsApp | Warm recovery message encouraging rebooking |
| Recovery logged | Backoffice > System Logs | agent=cancellation_recovery, action=recovery_sent, status=success |
| Message logged | DB: `whatsapp_messages` | direction=outbound, agent_source=cancellation_recovery |

### Verify via CLI

```bash
# Check booking status
php artisan tinker --execute="echo App\Models\Booking::latest()->first()?->booking_status"

# Check recovery was scheduled
php artisan tinker --execute="App\Models\SystemLog::where('agent','cancellation_recovery')->latest()->first(['action','status','payload','created_at'])?->toArray() |> print_r(\$value)"
```

### Pass Criteria
- Booking immediately marked as cancelled
- Recovery job scheduled with 30-minute delay
- Guest receives recovery message after 30 minutes
- Recovery message is warm and non-pushy, encourages rebooking

---

## Test 5: Booking Deletion

**Goal:** Verify that deleting a booking in Lodgify marks it cancelled without sending any message.

### Steps

1. Create a new booking in Lodgify (with phone number)
2. Wait for welcome message to arrive (confirms sync works)
3. Delete the booking in Lodgify (not cancel - delete)
4. Wait up to 60 seconds

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Status updated | Backoffice > Bookings | booking_status = cancelled |
| Deletion logged | Backoffice > System Logs | agent=lodgify_sync, action=booking_deleted |
| No recovery | Backoffice > System Logs | NO cancellation_recovery entry for this booking |
| No WhatsApp | Check test phone | NO recovery or cancellation message sent |

### Pass Criteria
- Booking marked cancelled
- No WhatsApp messages sent for deletion (unlike cancellation which triggers recovery)

---

## Test 6: Upsell Broadcast

**Goal:** Verify that checked-in guests receive upsell offers based on their day of stay.

### Setup

1. Ensure a booking exists with:
   - `booking_status` = `checked_in`
   - `check_in` = **today** (so the guest is "currently staying")
   - `check_out` = **3 days from today** (so there's room for multiple upsell days)
   - `guest_phone` set
2. Ensure at least one active Offer exists with a matching timing_rule:
   - For day 1: timing_rule = `arrival_day`
   - For day 2+: timing_rule = `day_2`, `day_3`, etc.
   - For last day: timing_rule = `day_1_before_checkout`

```bash
# Set booking to checked_in with today as check-in, checkout in 3 days
php artisan tinker --execute="App\Models\Booking::latest()->first()?->update(['booking_status' => 'checked_in', 'check_in' => now()->toDateString(), 'check_out' => now()->addDays(3)->toDateString()])"

# Verify offers exist
php artisan tinker --execute="App\Models\Offer::where('is_active', true)->get(['title','timing_rule','price'])->each(fn(\$o) => print(\$o->title.' | '.\$o->timing_rule.' | '.\$o->price.PHP_EOL))"
```

### Steps

1. Dispatch the upsell broadcast job:
   ```bash
   php artisan tinker --execute="dispatch(new App\Jobs\UpsellBroadcastJob)"
   ```
2. Ensure queue worker processes it:
   ```bash
   php artisan queue:work --queue=agents --once
   ```
3. Check test phone for upsell message

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Upsell sent | Check test phone WhatsApp | Warm, non-pushy offer message |
| Upsell logged | DB: `upsell_logs` | New entry with outcome=pending |
| Booking state | DB: `bookings` | current_upsell_offer_id set, upsell_offer_sent_at set |
| System log | Backoffice > System Logs | agent=upsell_cron, action=offer_sent, status=success |
| Message logged | DB: `whatsapp_messages` | direction=outbound, agent_source=upsell_cron |

### Verify via CLI

```bash
# Check upsell log
php artisan tinker --execute="print_r(App\Models\UpsellLog::latest()->first()?->only(['booking_id','offer_id','outcome','sent_at']))"

# Check booking upsell state
php artisan tinker --execute="\$b = App\Models\Booking::latest()->first(); echo 'Offer ID: '.\$b->current_upsell_offer_id.' Sent at: '.\$b->upsell_offer_sent_at"
```

### Pass Criteria
- Guest receives ONE upsell message
- Message is personalized (guest name, language)
- UpsellLog entry created with outcome=pending
- Booking tracks which offer was sent

---

## Test 7: Upsell Reply - Accept

**Goal:** Verify that accepting an upsell offer creates a revenue transaction.

### Steps

1. Complete Test 6 first (so there is a pending upsell)
2. From the test phone, reply: **"Yes, I'd like that please"**
3. Wait for AI response

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| AI replied | Check test phone WhatsApp | Confirmation message |
| Upsell classified | DB: `upsell_logs` | outcome=accepted, guest_reply populated |
| Revenue created | DB: `transactions` | type=income, category=upsell, amount matches offer price |
| State cleared | DB: `bookings` | current_upsell_offer_id = null, upsell_offer_sent_at = null |
| System log | Backoffice > System Logs | agent=upsell_recv, action=reply_received, payload contains classification=accepted |

### Verify via CLI

```bash
# Check upsell outcome
php artisan tinker --execute="print_r(App\Models\UpsellLog::latest()->first()?->only(['outcome','guest_reply','revenue_generated']))"

# Check transaction was created
php artisan tinker --execute="print_r(App\Models\Transaction::latest()->first()?->only(['type','category','amount','description']))"
```

### Pass Criteria
- UpsellLog outcome = accepted
- Transaction created with correct amount
- Booking upsell state cleared (ready for next offer)

---

## Test 8: Upsell Reply - Decline

**Goal:** Verify that declining an upsell clears state without creating a transaction.

### Steps

1. Run Test 6 again to send a new upsell offer
2. From the test phone, reply: **"No thanks, not interested"**
3. Wait for AI response

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| AI replied | Check test phone WhatsApp | Graceful acknowledgment |
| Upsell classified | DB: `upsell_logs` | outcome=declined |
| No transaction | DB: `transactions` | NO new transaction created |
| State cleared | DB: `bookings` | current_upsell_offer_id = null |

### Pass Criteria
- UpsellLog outcome = declined
- No revenue transaction created
- Guest receives polite acknowledgment

---

## Test 9: Staff Daily Briefing

**Goal:** Verify that the staff receives a daily briefing with arrivals and departures.

### Setup

Ensure bookings exist with today's dates:

```bash
# Create an arrival for today
php artisan tinker --execute="App\Models\Booking::factory()->create(['guest_name' => 'Arrival Test', 'check_in' => now()->toDateString(), 'check_out' => now()->addDays(3)->toDateString(), 'booking_status' => 'confirmed', 'suite_name' => 'Suite Jasmin', 'num_guests' => 2, 'guest_nationality' => 'FR'])"

# Create a departure for today
php artisan tinker --execute="App\Models\Booking::factory()->create(['guest_name' => 'Departure Test', 'check_in' => now()->subDays(2)->toDateString(), 'check_out' => now()->toDateString(), 'booking_status' => 'checked_in', 'suite_name' => 'Suite Rose'])"
```

### Steps

1. Dispatch the briefing job:
   ```bash
   php artisan tinker --execute="dispatch(new App\Jobs\StaffBriefingJob)"
   php artisan queue:work --queue=agents --once
   ```
2. Check the staff WhatsApp number

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Briefing received | Staff WhatsApp number | French-language briefing |
| Contains arrivals | Read the message | Lists "Arrival Test" with suite and guest details |
| Contains departures | Read the message | Lists "Departure Test" with suite |
| System log | Backoffice > System Logs | agent=staff_briefing, action=briefing_dispatched, status=success |

### Pass Criteria
- Staff receives briefing in French
- Arrivals and departures listed correctly
- Message formatted for WhatsApp readability

---

## Test 10: Fallback Reply (No Active Booking)

**Goal:** Verify that messages from unknown numbers receive a polite fallback and are rate-limited.

### Steps

1. Send a WhatsApp message to the concierge number from a phone that has NO active booking
2. Wait for response
3. Send a second message within 1 hour from the same number

### Expected Results

**First message:**

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Fallback sent | Check phone WhatsApp | Bilingual message (EN + FR) explaining no active booking found |
| System log | Backoffice > System Logs | agent=guest_reply, action=skipped, reason=no_active_booking |

**Second message (within 1 hour):**

| Check | How to Verify | Expected |
|-------|--------------|----------|
| No response | Check phone WhatsApp | No reply (rate limited) |
| System log | Backoffice > System Logs | agent=guest_reply, action=skipped, reason=fallback_rate_limited |

### Pass Criteria
- First message gets bilingual fallback
- Subsequent messages within 1 hour are silently ignored
- All events logged

---

## Test 11: Webhook Idempotency

**Goal:** Verify that duplicate webhooks from 2Chat do not cause duplicate processing.

### Steps

1. Send a message from the test phone
2. Note the AI response
3. Check `whatsapp_messages` for the message UUID

### Expected Results

| Check | How to Verify | Expected |
|-------|--------------|----------|
| Single response | Check WhatsApp | Only ONE reply, not multiple |
| UUID tracked | DB: `whatsapp_messages` | twochat_message_id populated and unique |

### Pass Criteria
- Each inbound message produces exactly one outbound reply
- Duplicate webhook deliveries are silently dropped

---

## Test Execution Order

Run tests in this order for a clean flow:

```
Test 1   -> Creates booking, verifies welcome (asks about arrival time)
Test 3a  -> Collect preferences through multi-turn conversation
Test 3   -> General AI replies after preferences complete
Test 2   -> Updates booking, verifies no duplicate welcome
Test 6   -> Sends upsell (set booking to checked_in first)
Test 7   -> Accepts upsell
Test 6   -> Sends another upsell
Test 8   -> Declines upsell
Test 9   -> Staff briefing
Test 4   -> Cancels booking, verifies recovery (30 min wait)
Test 5   -> Creates + deletes another booking
Test 10  -> Fallback from unknown number
Test 11  -> Idempotency check
```

---

## Monitoring During Tests

Keep these running in separate terminals:

```bash
# Terminal 1: Queue worker
php artisan queue:work --queue=agents --verbose

# Terminal 2: Live Laravel log
tail -f storage/logs/laravel.log

# Terminal 3: Watch webhook logs (run after each Lodgify action)
php artisan tinker --execute="App\Models\WebhookLog::latest()->take(3)->get(['source','status_code','created_at'])->each(fn(\$w) => print(\$w->source.' | '.\$w->status_code.' | '.\$w->created_at.PHP_EOL))"
```

---

## Summary Checklist

| # | Test | Status |
|---|------|--------|
| 1 | New Booking -> Welcome Message | |
| 2 | Booking Update (no duplicate welcome) | |
| 3 | Guest Reply -> AI Concierge | |
| 3a | Preference Collection (multi-turn) | |
| 4 | Booking Cancellation -> Recovery | |
| 5 | Booking Deletion | |
| 6 | Upsell Broadcast | |
| 7 | Upsell Reply - Accept | |
| 8 | Upsell Reply - Decline | |
| 9 | Staff Daily Briefing | |
| 10 | Fallback Reply (unknown number) | |
| 11 | Webhook Idempotency | |
