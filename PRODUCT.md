# E-Conciergerie

AI-powered property management automation backend for **Riad Larbi Khalis**, a 4-suite boutique riad in Marrakech, Morocco. The system automates guest WhatsApp communication, upsell campaigns, staff briefing dispatch, financial tracking, and full audit logging of every agent action.

---

## 1. Database Schema

Generate one migration file per table in dependency order. Every table must have `timestamps()` and a clean `down()` method.

---

### 1.1 `offers`

The upsell offer catalog. Must exist before bookings (FK dependency).

```
id                      bigIncrements
offer_code              string, unique        slug e.g. HAMMAM_D2
title                   string
description             text                  full text passed to Claude
category                string                wellness / dining / experience / transport
timing_rule             string                e.g. day_2 / arrival_day / day_1_before_checkout
price                   decimal(8,2), nullable
currency                string(3), default MAD
is_active               boolean, default true
max_sends_per_stay      tinyInteger, default 1
timestamps
```

Index: `timing_rule`, `is_active`

---

### 1.2 `bookings`

Central guest booking record. Replaces the Active Bookings Google Sheet.

```
id                          bigIncrements
lodgify_booking_id          string, unique        from Lodgify webhook payload
guest_name                  string
guest_phone                 string                E.164 WhatsApp number e.g. +212XXXXXXXX
guest_email                 string, nullable
guest_nationality           string, nullable
num_guests                  tinyInteger
suite_name                  string                e.g. Suite Al Andalus
check_in                    date
check_out                   date
num_nights                  tinyInteger
booking_source              string                Airbnb / Direct / Booking.com / etc.
booking_status              string                confirmed / checked_in / checked_out / cancelled
total_amount                decimal(10,2)
currency                    string(3), default MAD
special_requests            text, nullable
internal_notes              text, nullable
lodgify_synced_at           timestamp, nullable
current_upsell_offer_id     foreignId, nullable, FK to offers.id
upsell_offer_sent_at        timestamp, nullable
timestamps
```

Index: `guest_phone`, `booking_status`, `check_in`, `check_out`

---

### 1.3 `upsell_logs`

Full history of every upsell offer sent and the guest's response.

```
id                  bigIncrements
booking_id          foreignId, FK bookings.id
offer_id            foreignId, FK offers.id
message_sent        text                  exact WhatsApp message Claude generated
sent_at             timestamp
guest_reply         text, nullable
reply_received_at   timestamp, nullable
outcome             string, nullable      accepted / declined / no_reply / pending
revenue_generated   decimal(8,2), nullable
timestamps
```

Index: `booking_id`, `offer_id`, `outcome`

---

### 1.4 `transactions`

Financial ledger. Replaces the Accounting Google Sheet.

```
id                  bigIncrements
booking_id          foreignId, nullable, FK bookings.id
type                string                income / expense
category            string                room_revenue / upsell / supplies / staff / maintenance
description         string
amount              decimal(10,2)
currency            string(3), default MAD
transaction_date    date
payment_method      string, nullable      cash / card / bank_transfer
reference           string, nullable      invoice or receipt number
recorded_by         string, nullable      staff name or "system"
timestamps
```

Index: `type`, `category`, `transaction_date`, `booking_id`

---

### 1.5 `whatsapp_messages`

Full message history per guest. Source of truth for all WhatsApp communication.

```
id                      bigIncrements
booking_id              foreignId, nullable, FK bookings.id
direction               string            inbound / outbound
phone_number            string            E.164
message_body            text
agent_source            string, nullable  which agent sent/processed this message
twochat_message_id      string, nullable, unique  2Chat message ID for deduplication
sent_at                 timestamp, nullable
received_at             timestamp, nullable
timestamps
```

Index: `phone_number`, `booking_id`, `direction`

---

### 1.6 `system_logs`

Audit trail for every agent action. Replaces Make.com execution history.

```
id              bigIncrements
agent           string        upsell_cron / upsell_recv / guest_reply / staff_briefing
action          string        offer_sent / reply_received / briefing_dispatched / skipped
booking_id      foreignId, nullable, FK bookings.id
payload         json, nullable full context snapshot at time of action
status          string        success / failed / skipped
error_message   text, nullable
duration_ms     integer, nullable
timestamps
```

Index: `agent`, `status`, `booking_id`, `created_at`

---

## 2. Eloquent Models

Generate a model for every table above. Each model must have:
- Explicit `$fillable` array (no `$guarded = []`)
- All relationships defined (`hasMany`, `belongsTo`)
- Casts: `payload` → `array` on SystemLog, `is_active` → `boolean` on Offer, `check_in`/`check_out` → `date` on Booking
- A `currentDayOfStay()` accessor on Booking that calculates how many days into the stay today is (returns null if not checked in)

**Relationships to implement:**
- `Booking` hasMany `UpsellLog`, `WhatsappMessage`, `Transaction`, `SystemLog`
- `Booking` belongsTo `Offer` (via `current_upsell_offer_id`)
- `Offer` hasMany `UpsellLog`
- `UpsellLog` belongsTo `Booking`, `Offer`

---

## 3. Contracts & Services

Follow the existing Contract → Service pattern with constructor injection via `ContractProvider`.

---

### 3.1 Service Architecture

```
app/Contract/
├── Concierge/
│   ├── BookingContract.php
│   ├── OfferContract.php
│   ├── UpsellLogContract.php
│   ├── TransactionContract.php
│   ├── WhatsappMessageContract.php
│   └── SystemLogContract.php

app/Service/
├── Concierge/
│   ├── BookingService.php        extends BaseService
│   ├── OfferService.php          extends BaseService
│   ├── UpsellLogService.php      extends BaseService
│   ├── TransactionService.php    extends BaseService
│   ├── WhatsappMessageService.php extends BaseService
│   └── SystemLogService.php      extends BaseService
├── WhatsApp/
│   └── TwoChatService.php        (already exists, enhance with error handling)
└── Lodgify/
    └── LodgifyService.php        (already exists)
```

Each Contract extends `BaseContract`. Each Service extends `BaseService`, passes the model to `parent::__construct()`, and sets `$relation` for default eager loading.

Register all bindings in `ContractProvider`:

```php
// Concierge
BookingContract::class => BookingService::class,
OfferContract::class => OfferService::class,
UpsellLogContract::class => UpsellLogService::class,
TransactionContract::class => TransactionService::class,
WhatsappMessageContract::class => WhatsappMessageService::class,
SystemLogContract::class => SystemLogService::class,
```

---

### 3.2 TwoChatService Enhancement

Enhance the existing `app/Service/WhatsApp/TwoChatService.php`:

```
- sendMessage(string $phone, string $message): array
- On failure, log to system_logs with status: failed
- Return the 2Chat response payload including message_uuid
- Throw TwoChatException on HTTP failure
```

---

## 4. AI Agents (Laravel AI SDK)

All agents use the `Laravel\Ai` SDK with `Promptable` + `RemembersConversations` traits. Each agent is an Agent class under `app/Ai/Agents/`.

The system prompt is stored in the `settings` table under key `concierge_system_prompt` and loaded via `Setting::where('key', ...)->value('value')`.

---

### Agent 1: GuestReplyAgent

**Trigger:** POST webhook from 2Chat when a guest sends any WhatsApp message.

**Route:** `POST /whatsapp/webhook` (existing, refactor controller)

**Flow:**
1. Validate the incoming 2Chat payload. Extract `phone_number` and `message_body`.
2. Look up an active booking by `guest_phone` where `booking_status` IN (`confirmed`, `checked_in`).
3. Store the raw message in `whatsapp_messages` (direction: `inbound`).
4. **Upsell detection:** If `bookings.current_upsell_offer_id` is not null AND `bookings.upsell_offer_sent_at` was within the last 48 hours, hand off to UpsellReplyAgent (Agent 4) instead.
5. Pass the message to the AI agent with context:
   - Guest name, suite, check-in/check-out dates, special requests
   - Last 10 inbound/outbound messages from `whatsapp_messages` for this booking
   - System prompt from `settings` table
6. Send AI response back via `TwoChatService::sendMessage()`.
7. Store AI response in `whatsapp_messages` (direction: `outbound`, agent_source: `guest_reply`).
8. Write a row to `system_logs` (agent: `guest_reply`, action: `reply_sent`, status: `success`).

**If no booking found:** Reply with a fallback message in English and French. Log as `skipped`.

---

### Agent 2: StaffBriefingAgent

**Trigger:** Laravel Scheduler, daily at 08:00 Morocco time (Africa/Casablanca).

**Flow:**
1. Query all bookings where `check_in = today` OR `check_out = today`.
2. Generate a structured staff briefing via AI:
   - Arrivals: guest name, suite, num_guests, check-in time if known, special requests, nationality
   - Departures: guest name, suite, any outstanding balance notes
3. Send the briefing as a single WhatsApp message to the staff group number (`STAFF_WHATSAPP_NUMBER` env).
4. Log to `system_logs` (agent: `staff_briefing`, action: `briefing_dispatched`).

---

### Agent 3: UpsellBroadcastJob

**Trigger:** Laravel Scheduler, twice daily at 09:00 and 15:00 Morocco time.

**Implements `ShouldQueue`**, dispatched to the `agents` queue.

**Flow:**
1. Query all bookings where `booking_status = checked_in`.
2. For each booking:
   a. Calculate `current_day_of_stay` using the Booking model accessor.
   b. Fetch all active offers.
   c. Filter offers where `timing_rule` matches `current_day_of_stay` AND the offer has not already been sent this stay (check `upsell_logs` for this `booking_id` + `offer_id`).
   d. If no matching offer, skip and log `NO_OFFER`.
   e. AI selects the best offer and writes a personalized WhatsApp message in the guest's language.
   f. Send via `TwoChatService::sendMessage()`.
   g. Update `bookings.current_upsell_offer_id` and `bookings.upsell_offer_sent_at`.
   h. Insert row into `upsell_logs` (outcome: `pending`).
   i. Store message in `whatsapp_messages` (direction: `outbound`, agent_source: `upsell_cron`).
   j. Log to `system_logs`.

---

### Agent 4: UpsellReplyAgent

**Trigger:** Called from Agent 1 (GuestReplyAgent) when the inbound message is a reply to a pending upsell offer.

**Flow:**
1. Read `current_upsell_offer_id` from the booking.
2. AI classifies the guest reply as: `accepted`, `declined`, or `unclear`.
3. If `accepted`:
   - Update `upsell_logs.outcome = accepted`
   - Insert a `transactions` row (type: `income`, category: `upsell`)
   - Reply to guest confirming the booking
4. If `declined`:
   - Update `upsell_logs.outcome = declined`
   - Reply warmly, no pressure
5. If `unclear`:
   - Ask a clarifying follow-up
6. Clear `bookings.current_upsell_offer_id` after resolution.
7. Log to `system_logs`.

---

### Agent 5: CancellationRecoveryAgent

**Trigger:** POST webhook from Lodgify when a booking status changes to `cancelled`.

**Route:** `POST /lodgify/webhook` (existing, differentiated by action field)

**Flow:**
1. Parse Lodgify payload. If status is not `cancelled`, update the booking record and exit.
2. If cancelled, dispatch a delayed job (30 minutes).
3. AI generates a warm, non-pushy recovery message offering a discount or alternative dates.
4. Send to guest via `TwoChatService::sendMessage()`.
5. Log to `system_logs` (agent: `cancellation_recovery`, action: `recovery_sent`).

---

### Agent 6: LodgifySyncAgent

**Trigger:** POST webhook from Lodgify on booking create or update.

**Route:** `POST /lodgify/webhook` (same route as Agent 5, differentiated by action field)

**Flow:**
1. Parse the Lodgify webhook payload.
2. Upsert into `bookings` using `lodgify_booking_id` as the unique key.
3. Map Lodgify fields to the bookings schema. Store `lodgify_synced_at = now()`.
4. If this is a new confirmed booking, dispatch a welcome message: AI writes a warm welcome in the guest's likely language (infer from nationality if available).
5. Log to `system_logs`.

---

## 5. Controllers

Follow the existing pattern: thin controllers with injected Contracts. All business logic lives in Services or Jobs.

---

### 5.1 Webhook Controllers (Refactor Existing)

**`app/Http/Controllers/WhatsApp/WebhookController.php`** (refactor)
- Validate `X-Webhook-Secret` header against `env('WEBHOOK_SECRET')`
- Extract payload, dispatch to GuestReplyAgent flow
- Return `response()->json(['status' => 'ok'])`

**`app/Http/Controllers/Lodgify/WebhookController.php`** (refactor)
- Keep the existing `match($action)` pattern
- Route `booking_new` / `booking_change` to LodgifySyncAgent
- Route `booking_cancelled` to CancellationRecoveryAgent
- Keep `rate_change`, `availability_change`, `guest_message_received` handlers

---

### 5.2 Backoffice CRUD Controllers

Follow the existing controller pattern: constructor-inject the Contract, standard CRUD methods.

```
app/Http/Controllers/Concierge/
├── BookingController.php
├── OfferController.php
├── UpsellLogController.php      (index + fetch only, read-only)
├── TransactionController.php
└── SystemLogController.php      (index + fetch only, read-only)
```

Each controller:
- `index()` → `Inertia::render('concierge/{resource}/index')`
- `fetch()` → `response()->json($this->service->all(...))`
- `create()` → `Inertia::render('concierge/{resource}/form')`
- `store(FormRequest)` → `$this->service->create($request->validated())`; `WebResponse::response($data, 'route.name')`
- `show($id)` → `Inertia::render('concierge/{resource}/form', ['resource' => $data])`
- `update(FormRequest, $id)` → `$this->service->update($id, $request->validated())`; `WebResponse::response(...)`
- `destroy($id)` / `destroy_bulk(Request)` → `WebResponse::response(...)`

---

### 5.3 Form Requests

```
app/Http/Requests/Concierge/
├── BookingRequest.php
├── OfferRequest.php
├── TransactionRequest.php
```

Follow existing pattern: `authorize() → true`, `rules()` with explicit validation, `Rule::unique()->ignore()` for updates.

---

## 6. Routes

Follow existing pattern: grouped by domain with middleware and prefix.

---

### 6.1 Webhook Routes (API middleware, no auth)

Already in `routes/api/`. Refactor to add webhook secret validation.

```php
// routes/api/whatsapp.php
Route::post('/whatsapp/webhook', [WebhookController::class, 'handle'])
    ->name('whatsapp.webhook');

// routes/api/lodgify.php
Route::post('/lodgify/webhook', [WebhookController::class, 'handle'])
    ->name('lodgify.webhook');
```

---

### 6.2 Backoffice Routes

```php
// routes/web/concierge.php
Route::group(['middleware' => 'auth', 'prefix' => 'concierge', 'as' => 'backoffice.concierge.'], function () {

    Route::group(['prefix' => 'booking', 'as' => 'booking.'], function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::get('/fetch', [BookingController::class, 'fetch'])->name('fetch');
        Route::get('/create', [BookingController::class, 'create'])->name('create');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::get('/{id}', [BookingController::class, 'show'])->name('show');
        Route::put('/{id}', [BookingController::class, 'update'])->name('update');
        Route::delete('/{id}', [BookingController::class, 'destroy'])->name('destroy');
        Route::post('/destroy-bulk', [BookingController::class, 'destroy_bulk'])->name('destroy-bulk');
    });

    Route::group(['prefix' => 'offer', 'as' => 'offer.'], function () {
        // Same CRUD pattern
    });

    Route::group(['prefix' => 'upsell-log', 'as' => 'upsell-log.'], function () {
        Route::get('/', [UpsellLogController::class, 'index'])->name('index');
        Route::get('/fetch', [UpsellLogController::class, 'fetch'])->name('fetch');
        // Read-only: no create, store, update, destroy
    });

    Route::group(['prefix' => 'transaction', 'as' => 'transaction.'], function () {
        // Full CRUD pattern
    });

    Route::group(['prefix' => 'system-log', 'as' => 'system-log.'], function () {
        Route::get('/', [SystemLogController::class, 'index'])->name('index');
        Route::get('/fetch', [SystemLogController::class, 'fetch'])->name('fetch');
        // Read-only: no create, store, update, destroy
    });
});
```

---

## 7. Frontend (Inertia + React + TypeScript)

Follow existing patterns: `IndexPage` component for list views, `useForm` + `FormResponse` for forms, TanStack React Table columns, AppLayout.

---

### 7.1 TypeScript Types

```
resources/js/types/
├── booking.ts
├── offer.ts
├── upsell-log.ts
├── transaction.ts
├── system-log.ts
└── whatsapp-message.ts
```

Each type extends `Model` from `types/model.ts`:

```typescript
export type Booking = Model & {
    lodgify_booking_id: string;
    guest_name: string;
    guest_phone: string;
    guest_email: string | null;
    guest_nationality: string | null;
    num_guests: number;
    suite_name: string;
    check_in: string;
    check_out: string;
    num_nights: number;
    booking_source: string;
    booking_status: string;
    total_amount: string;
    currency: string;
    special_requests: string | null;
    internal_notes: string | null;
    current_upsell_offer_id: number | null;
    upsell_offer_sent_at: string | null;
    // Relationships
    current_offer?: Offer;
    upsell_logs?: UpsellLog[];
    whatsapp_messages?: WhatsappMessage[];
};
```

---

### 7.2 Pages

```
resources/js/pages/concierge/
├── booking/
│   ├── index.tsx          IndexPage with columns + status badges
│   └── form.tsx           useForm with all booking fields, suite as select
├── offer/
│   ├── index.tsx          IndexPage with active toggle column
│   └── form.tsx           useForm with textarea for description
├── upsell-log/
│   └── index.tsx          IndexPage, read-only (hideAdd, no action column)
├── transaction/
│   ├── index.tsx          IndexPage with type badge (income=green, expense=red)
│   └── form.tsx           useForm with all transaction fields
└── system-log/
    └── index.tsx          IndexPage, read-only, payload shown in detail
```

**Column patterns:**
- Status fields → Badge component with color mapping
- Boolean fields → Toggle or colored dot
- Dates → `createDateColumn()` helper
- Actions → `createActionColumn()` helper
- Monetary fields → formatted with currency

**Form patterns:**
- Use `useForm` from `@inertiajs/react`
- Submit via `post(store().url, FormResponse)` / `put(update(id).url, FormResponse)`
- Cancel button → `router.visit(index().url)`
- Suite names as select: `Suite Al Andalus`, `Suite Zitoun`, `Suite Atlas`, `Suite Menara`

---

### 7.3 Booking Detail View

The booking `show` / `form` page includes two read-only panels below the form:

1. **WhatsApp Timeline** — all `whatsapp_messages` for this booking, newest first. Show direction (inbound/outbound), timestamp, agent_source, message_body.
2. **Upsell History** — all `upsell_logs` for this booking. Show offer title, sent_at, outcome (badge), guest_reply, revenue.

Include a "Send Manual Message" button that opens a modal with a text input and sends via `TwoChatService`.

---

## 8. Dashboard

Add a dashboard page at `backoffice/concierge` (or update the existing `backoffice` page) with:

- **Today's Arrivals** — table of bookings where `check_in = today`
- **Today's Departures** — table of bookings where `check_out = today`
- **Currently Checked In** — count + list of active guests
- **Upsell Performance This Month** — acceptance rate, revenue
- **Recent System Logs** — last 10 entries
- **Revenue Chart** — bar chart of daily income vs expense for last 30 days (use `recharts`, already installed)

Controller returns all data as Inertia props. Frontend renders using existing card/table components + recharts `BarChart`.

---

## 9. Environment Variables

Add to `.env`:

```
# Anthropic
ANTHROPIC_API_KEY=

# 2Chat WhatsApp Bridge
TWOCHAT_API_KEY=
TWOCHAT_PHONE_NUMBER=+212XXXXXXXXX
STAFF_WHATSAPP_NUMBER=+212XXXXXXXXX

# Lodgify
LODGIFY_API_KEY=
LODGIFY_WEBHOOK_SECRET=

# Webhooks
WEBHOOK_SECRET=

# App
APP_TIMEZONE=Africa/Casablanca
```

Set `APP_TIMEZONE=Africa/Casablanca` in `config/app.php`.

---

## 10. Seeders

**OfferSeeder** — 8 offers:
- `arrival_day`: Welcome drink and date platter in the suite
- `day_2`: Traditional hammam and gommage for two
- `day_2`: Private rooftop dinner under the stars
- `day_3`: Half-day Medina guided walking tour
- `day_3`: Camel trek at sunset, Palmeraie
- `day_4`: Moroccan cooking class with the chef
- `day_1_before_checkout`: Late checkout until 14:00
- `day_1_before_checkout`: Airport transfer by private car

**BookingSeeder** — 12 bookings spread across June to August 2026, covering all 4 suites, mixed nationalities, mixed sources (Airbnb, Direct, Booking.com), mix of statuses. Include 2 cancelled bookings.

**UpsellLogSeeder** — Attach a few upsell log records to checked-in bookings with varied outcomes.

Update `DatabaseSeeder` to call all seeders.

---

## 11. Code Quality Requirements

- All controllers must be thin. Business logic lives in Service classes or Jobs.
- All agent Jobs must implement `ShouldQueue` and be dispatched to the `agents` queue.
- Use Form Request classes for webhook payload validation.
- No raw SQL. Eloquent only.
- Use Laravel's `rescue()` helper inside Jobs to catch exceptions without crashing the queue.
- Services return `Exception` on failure (matching BaseService pattern). Controllers check with `WebResponse::response()`.
- Run `php artisan migrate:fresh --seed` as the final validation step.
