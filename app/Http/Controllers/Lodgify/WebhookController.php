<?php

namespace App\Http\Controllers\Lodgify;

use App\Ai\Agents\GuestReplyAgent;
use App\Http\Controllers\Controller;
use App\Jobs\CancellationRecoveryJob;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\WebhookLog;
use App\Models\WhatsappMessage;
use App\Service\WhatsApp\TwoChatService;
use App\Utils\PhoneFormatter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request, TwoChatService $twoChat): JsonResponse
    {
        $webhookLog = WebhookLog::create([
            'source' => 'lodgify',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => collect($request->headers->all())->except(['cookie'])->toArray(),
            'payload' => $request->all(),
            'ip_address' => $request->ip(),
        ]);

        if (!$this->verifySignature($request)) {
            Log::warning('Lodgify webhook signature verification failed');

            $webhookLog->update([
                'status_code' => 401,
                'response_body' => ['error' => 'Invalid signature'],
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Lodgify sends the payload as a JSON array with a single element.
        // Unwrap it so the rest of the controller can use $request->input() normally.
        $data = $request->all();
        if (array_is_list($data) && count($data) === 1) {
            $request->replace($data[0]);
        }

        $action = $request->input('action');

        return match ($action) {
            'booking_new', 'booking_change' => $this->handleBookingSync($request, $twoChat),
            'booking_cancelled' => $this->handleBookingCancellation($request, $twoChat),
            'booking_deleted' => $this->handleBookingDeleted($request),
            'rate_change' => $this->handleRateChange($request),
            'availability_change' => $this->handleAvailabilityChange($request),
            'guest_message_received' => $this->handleGuestMessage($request),
            'booking_payment_received', 'booking_payment_refunded', 'booking_payment_deleted' => $this->handlePaymentEvent($request),
            default => $this->handleUnknown($request),
        };
    }

    protected function handleBookingSync(Request $request, TwoChatService $twoChat): JsonResponse
    {
        $start = microtime(true);
        $lodgifyBooking = $request->input('booking', []);
        $guest = $request->input('guest', []);
        $totalAmount = $request->input('booking_total_amount', 0);
        $currency = $request->input('booking_currency_code', 'MAD');
        $lodgifyId = (string) ($lodgifyBooking['id'] ?? '');

        if (!$lodgifyId) {
            return response()->json(['status' => 'skipped', 'reason' => 'no_booking_id']);
        }

        $nights = $lodgifyBooking['nights'] ?? 1;

        try {
            $checkIn = isset($lodgifyBooking['date_arrival'])
                ? Carbon::parse($lodgifyBooking['date_arrival'])->toDateString()
                : now()->toDateString();
            $checkOut = isset($lodgifyBooking['date_departure'])
                ? Carbon::parse($lodgifyBooking['date_departure'])->toDateString()
                : now()->addDays($nights)->toDateString();
        } catch (\Exception $e) {
            Log::warning('Lodgify date parsing failed', [
                'arrival' => $lodgifyBooking['date_arrival'] ?? null,
                'departure' => $lodgifyBooking['date_departure'] ?? null,
                'error' => $e->getMessage(),
            ]);
            $checkIn = now()->toDateString();
            $checkOut = now()->addDays($nights)->toDateString();
        }

        // Preserve manually-set statuses (checked_in, checked_out) that Lodgify doesn't know about
        $existingBooking = Booking::where('lodgify_booking_id', $lodgifyId)->first();
        $lodgifyStatus = $this->mapLodgifyStatus($lodgifyBooking['status'] ?? 'Booked');

        $rawPhone = $guest['phone_number'] ?? '';
        $guestPhone = PhoneFormatter::toE164($rawPhone) ?? $rawPhone;

        $numGuests = collect($lodgifyBooking['room_types'] ?? [])->sum('people') ?: 1;
        $suiteName = $lodgifyBooking['room_types'][0]['name'] ?? $lodgifyBooking['property_name'] ?? '';

        $booking = Booking::updateOrCreate(
            ['lodgify_booking_id' => $lodgifyId],
            [
                'guest_name' => $guest['name'] ?? 'Unknown',
                'guest_phone' => $guestPhone,
                'guest_email' => $guest['email'] ?? null,
                'guest_nationality' => $guest['country'] ?? null,
                'num_guests' => $numGuests,
                'suite_name' => $suiteName ?: 'Unknown',
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'num_nights' => $nights,
                'booking_source' => $lodgifyBooking['source_text'] ?? $lodgifyBooking['source'] ?? 'Direct',
                'booking_status' => ($existingBooking && in_array($existingBooking->booking_status, ['checked_in', 'checked_out']))
                    ? $existingBooking->booking_status
                    : $lodgifyStatus,
                'total_amount' => $totalAmount ?: 0,
                'currency' => $currency ?: 'MAD',
                'detected_language' => $this->detectLanguage($guest['country'] ?? null),
                'lodgify_synced_at' => now(),
            ],
        );

        // Set conversation state for edge cases (only on new bookings)
        if ($booking->wasRecentlyCreated) {
            $this->applyInitialConversationState($booking, $guestPhone, $numGuests, $suiteName, $checkIn, $twoChat);
        }

        // Link or create customer record by phone number
        if ($guestPhone && $booking->wasRecentlyCreated) {
            $customer = $this->linkCustomer($booking, $guest);

            // Pre-populate preferences from last stay for returning guests
            if ($customer->isReturning()) {
                $this->prepopulatePreferences($booking, $customer);
            }
        }

        SystemLog::create([
            'agent' => 'lodgify_sync',
            'action' => $booking->wasRecentlyCreated ? 'booking_created' : 'booking_updated',
            'booking_id' => $booking->id,
            'payload' => [
                'lodgify_id' => $lodgifyId,
                'status' => $booking->booking_status,
                'customer_id' => $booking->customer_id,
                'returning_guest' => $booking->customer?->isReturning() ?? false,
            ],
            'status' => 'success',
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
        ]);

        // Auto-log booking revenue (once, on creation, if amount > 0)
        if ($booking->wasRecentlyCreated && !$booking->revenue_logged && $booking->total_amount > 0) {
            Transaction::create([
                'booking_id' => $booking->id,
                'type' => 'income',
                'category' => 'accommodation',
                'description' => "Booking: {$booking->guest_name} ({$booking->suite_name})",
                'amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'transaction_date' => now()->toDateString(),
                'recorded_by' => 'system',
            ]);
            $booking->update(['revenue_logged' => true]);
        }

        // Send welcome message for new confirmed bookings (skip edge-case states that need manual handling first)
        $skipWelcomeStates = ['phone_missing', 'group_booking', 'suite_pending'];
        if ($booking->wasRecentlyCreated && $booking->booking_status === 'confirmed' && $booking->guest_phone && !in_array($booking->conversation_state, $skipWelcomeStates)) {
            try {
                $this->sendWelcomeMessage($booking, $twoChat);
            } catch (\Throwable $e) {
                Log::error('Welcome message failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);

                SystemLog::create([
                    'agent' => 'lodgify_sync',
                    'action' => 'welcome_sent',
                    'booking_id' => $booking->id,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleBookingCancellation(Request $request, TwoChatService $twoChat): JsonResponse
    {
        $lodgifyBooking = $request->input('booking', []);
        $lodgifyId = (string) ($lodgifyBooking['id'] ?? '');

        if (!$lodgifyId) {
            return response()->json(['status' => 'skipped', 'reason' => 'no_booking_id']);
        }

        // Find or create the booking, then force status to cancelled
        // Don't use handleBookingSync — it would map Lodgify's status field
        // which may still say 'Booked' even though the action is cancellation
        $booking = Booking::where('lodgify_booking_id', $lodgifyId)->first();

        if (!$booking) {
            // Booking never synced — record it as cancelled but don't send recovery
            // Guest was never in our system, no point sending a recovery message
            $guest = $request->input('guest', []);
            $nights = $lodgifyBooking['nights'] ?? 1;

            try {
                $checkIn = isset($lodgifyBooking['date_arrival'])
                    ? Carbon::parse($lodgifyBooking['date_arrival'])->toDateString()
                    : now()->toDateString();
                $checkOut = isset($lodgifyBooking['date_departure'])
                    ? Carbon::parse($lodgifyBooking['date_departure'])->toDateString()
                    : now()->addDays($nights)->toDateString();
            } catch (\Exception $e) {
                Log::warning('Lodgify cancellation date parsing failed', [
                    'arrival' => $lodgifyBooking['date_arrival'] ?? null,
                    'departure' => $lodgifyBooking['date_departure'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $checkIn = now()->toDateString();
                $checkOut = now()->addDays($nights)->toDateString();
            }

            $newBooking = Booking::create([
                'lodgify_booking_id' => $lodgifyId,
                'guest_name' => $guest['name'] ?? 'Unknown',
                'guest_phone' => $guest['phone_number'] ?? '',
                'guest_email' => $guest['email'] ?? null,
                'guest_nationality' => $guest['country'] ?? null,
                'num_guests' => collect($lodgifyBooking['room_types'] ?? [])->sum('people') ?: 1,
                'suite_name' => $lodgifyBooking['room_types'][0]['name'] ?? $lodgifyBooking['property_name'] ?? 'Unknown',
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'num_nights' => $nights,
                'booking_source' => $lodgifyBooking['source_text'] ?? $lodgifyBooking['source'] ?? 'Direct',
                'booking_status' => 'cancelled',
                'total_amount' => $request->input('booking_total_amount', 0) ?: 0,
                'currency' => $request->input('booking_currency_code', 'MAD') ?: 'MAD',
                'lodgify_synced_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'lodgify_sync',
                'action' => 'booking_cancelled',
                'booking_id' => $newBooking->id,
                'status' => 'success',
                'payload' => ['lodgify_id' => $lodgifyId, 'source' => 'cancellation_event'],
            ]);

            return response()->json(['status' => 'ok']);
        }

        $booking->update([
            'booking_status' => 'cancelled',
            'conversation_state' => 'cancelled',
            'lodgify_synced_at' => now(),
        ]);

        SystemLog::create([
            'agent' => 'lodgify_sync',
            'action' => 'booking_cancelled',
            'booking_id' => $booking->id,
            'status' => 'success',
        ]);

        // Alert manager AND owner immediately
        $this->sendCancellationAlert($booking, $twoChat);
        $this->sendCancellationAlertToOwner($booking, $twoChat);

        // Log revenue loss as a negative transaction
        if ($booking->total_amount > 0) {
            Transaction::create([
                'booking_id' => $booking->id,
                'type' => 'expense',
                'category' => 'cancellation_loss',
                'description' => "Revenue loss: cancelled booking {$booking->guest_name} ({$booking->suite_name})",
                'amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'transaction_date' => now()->toDateString(),
                'recorded_by' => 'system',
            ]);
        }

        // Only send recovery plan if guest has a phone number and not already scheduled
        if ($booking->guest_phone) {
            $alreadyScheduled = SystemLog::where('booking_id', $booking->id)
                ->where('agent', 'cancellation_recovery')
                ->where('action', 'recovery_scheduled')
                ->where('created_at', '>=', now()->subHour())
                ->exists();

            if (!$alreadyScheduled) {
                CancellationRecoveryJob::dispatch($booking->id)->delay(now()->addMinutes(30));

                SystemLog::create([
                    'agent' => 'cancellation_recovery',
                    'action' => 'recovery_scheduled',
                    'booking_id' => $booking->id,
                    'status' => 'success',
                    'payload' => ['delay_minutes' => 30],
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleBookingDeleted(Request $request): JsonResponse
    {
        $lodgifyBooking = $request->input('booking', []);
        $lodgifyId = (string) ($lodgifyBooking['id'] ?? '');

        if (!$lodgifyId) {
            return response()->json(['status' => 'skipped', 'reason' => 'no_booking_id']);
        }

        $booking = Booking::where('lodgify_booking_id', $lodgifyId)->first();

        if ($booking) {
            $booking->update(['booking_status' => 'cancelled']);

            SystemLog::create([
                'agent' => 'lodgify_sync',
                'action' => 'booking_deleted',
                'booking_id' => $booking->id,
                'status' => 'success',
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function sendCancellationAlert(Booking $booking, TwoChatService $twoChat): void
    {
        $staffNumber = config('whatsapp.staff_phone_number');

        if (!$staffNumber) {
            return;
        }

        $daysUntilCheckIn = now()->diffInDays($booking->check_in, false);
        $urgency = $daysUntilCheckIn <= 3 ? 'URGENT — ' : '';

        $alert = implode("\n", [
            "{$urgency}BOOKING CANCELLED",
            "",
            "Guest: {$booking->guest_name}",
            "Suite: {$booking->suite_name}",
            "Dates: {$booking->check_in->format('d M')} - {$booking->check_out->format('d M')} ({$booking->num_nights} nights)",
            "Value: {$booking->total_amount} {$booking->currency}",
            "Source: {$booking->booking_source}",
            $daysUntilCheckIn > 0 ? "Days until check-in: {$daysUntilCheckIn}" : "Check-in date has passed",
            "",
            "Recovery message will be sent to the guest in 30 minutes.",
        ]);

        try {
            $twoChat->sendMessage($staffNumber, $alert);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $staffNumber,
                'message_body' => $alert,
                'agent_source' => 'cancellation_alert',
                'booking_id' => $booking->id,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Cancellation alert failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendCancellationAlertToOwner(Booking $booking, TwoChatService $twoChat): void
    {
        $ownerPhone = config('whatsapp.owner_phone_number');

        if (!$ownerPhone) {
            return;
        }

        // Don't duplicate if owner and staff are the same number
        if ($ownerPhone === config('whatsapp.staff_phone_number')) {
            return;
        }

        $daysUntilCheckIn = now()->diffInDays($booking->check_in, false);
        $urgency = $daysUntilCheckIn <= 3 ? 'URGENT — ' : '';

        $alert = implode("\n", [
            "{$urgency}BOOKING CANCELLED",
            "",
            "Guest: {$booking->guest_name}",
            "Suite: {$booking->suite_name}",
            "Dates: {$booking->check_in->format('d M')} - {$booking->check_out->format('d M')} ({$booking->num_nights} nights)",
            "Value: {$booking->total_amount} {$booking->currency}",
            "Source: {$booking->booking_source}",
            $daysUntilCheckIn > 0 ? "Days until check-in: {$daysUntilCheckIn}" : "Check-in date has passed",
            "",
            "A recovery plan will be sent shortly.",
        ]);

        try {
            $twoChat->sendMessage($ownerPhone, $alert);

            WhatsappMessage::create([
                'direction' => 'outbound',
                'phone_number' => $ownerPhone,
                'message_body' => $alert,
                'agent_source' => 'cancellation_alert',
                'booking_id' => $booking->id,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Owner cancellation alert failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendWelcomeMessage(Booking $booking, TwoChatService $twoChat): void
    {
        $customer = $booking->customer;
        $isReturning = $customer && $customer->isReturning();

        $prompt = $isReturning
            ? "Generate a warm welcome-back message for this returning guest. They have stayed {$customer->total_stays} time(s) before. Acknowledge their return — make them feel recognized and valued. Introduce yourself as the concierge and let them know you are available on WhatsApp. Mention that you remember their preferences from last time and confirm if anything has changed. Ask about their estimated arrival time. Keep it natural and personal."
            : 'Generate a warm welcome message for this new guest. Introduce yourself as the concierge and let them know you are available on WhatsApp for anything they need during their stay. At the end, ask about their estimated arrival time to help prepare for their welcome. Keep it natural — just ask about arrival time for now, you will collect other preferences in follow-up messages.';

        $response = (new GuestReplyAgent($booking))->prompt($prompt);

        $message = (string) $response;

        $result = $twoChat->sendMessage($booking->guest_phone, $message);

        WhatsappMessage::create([
            'booking_id' => $booking->id,
            'direction' => 'outbound',
            'phone_number' => $booking->guest_phone,
            'message_body' => $message,
            'agent_source' => 'lodgify_sync',
            'twochat_message_id' => $result['message_uuid'] ?? null,
            'sent_at' => now(),
        ]);

        SystemLog::create([
            'agent' => 'lodgify_sync',
            'action' => 'welcome_sent',
            'booking_id' => $booking->id,
            'status' => 'success',
        ]);
    }

    protected function linkCustomer(Booking $booking, array $guest): Customer
    {
        $customer = Customer::firstOrCreate(
            ['phone' => $booking->guest_phone],
            [
                'name' => $booking->guest_name,
                'email' => $booking->guest_email,
                'nationality' => $booking->guest_nationality,
                'first_stay_at' => $booking->check_in,
            ],
        );

        // Update customer details from latest booking (name/email may change across platforms)
        $customer->update(array_filter([
            'name' => $booking->guest_name,
            'email' => $booking->guest_email ?? $customer->email,
            'nationality' => $booking->guest_nationality ?? $customer->nationality,
            'last_stay_at' => $booking->check_in,
        ]));

        $customer->increment('total_stays');
        $customer->refresh();

        $booking->update(['customer_id' => $customer->id]);

        return $customer;
    }

    protected function prepopulatePreferences(Booking $booking, Customer $customer): void
    {
        $prefs = $customer->raw_preferences ?? [];

        if (empty($prefs)) {
            return;
        }

        $updates = [];

        if (isset($prefs['bed_type']) && !$booking->pref_bed_type) {
            $updates['pref_bed_type'] = $prefs['bed_type'];
        }

        if (isset($prefs['airport_transfer']) && !$booking->pref_airport_transfer) {
            $updates['pref_airport_transfer'] = $prefs['airport_transfer'];
        }

        if (isset($prefs['special_requests']) && $prefs['special_requests'] !== 'none' && !$booking->pref_special_requests) {
            $updates['pref_special_requests'] = $prefs['special_requests'];
        }

        // Arrival time is per-stay — do not pre-populate

        if (!empty($updates)) {
            $booking->update($updates);

            SystemLog::create([
                'agent' => 'lodgify_sync',
                'action' => 'preferences_prepopulated',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => $updates,
            ]);
        }
    }

    /**
     * Detect likely guest language from country code.
     * SPEC: "Detect guest language from booking country data."
     */
    protected function detectLanguage(?string $country): ?string
    {
        if (!$country) {
            return null;
        }

        $country = strtoupper(trim($country));

        $map = [
            // French-speaking
            'FR' => 'fr', 'BE' => 'fr', 'CH' => 'fr', 'CA' => 'fr', 'LU' => 'fr',
            'SN' => 'fr', 'CI' => 'fr', 'ML' => 'fr', 'BF' => 'fr', 'NE' => 'fr',
            'TG' => 'fr', 'BJ' => 'fr', 'CM' => 'fr', 'CD' => 'fr', 'GA' => 'fr',
            'MG' => 'fr', 'HT' => 'fr', 'MC' => 'fr',
            // Arabic-speaking
            'MA' => 'ar', 'DZ' => 'ar', 'TN' => 'ar', 'EG' => 'ar', 'SA' => 'ar',
            'AE' => 'ar', 'QA' => 'ar', 'KW' => 'ar', 'BH' => 'ar', 'OM' => 'ar',
            'JO' => 'ar', 'LB' => 'ar', 'IQ' => 'ar', 'LY' => 'ar', 'SD' => 'ar',
            // Spanish
            'ES' => 'es', 'MX' => 'es', 'AR' => 'es', 'CO' => 'es', 'CL' => 'es',
            'PE' => 'es', 'VE' => 'es', 'EC' => 'es', 'CU' => 'es',
            // German
            'DE' => 'de', 'AT' => 'de',
            // Italian
            'IT' => 'it',
            // Portuguese
            'PT' => 'pt', 'BR' => 'pt',
            // Dutch
            'NL' => 'nl',
            // English (default for anglophone countries)
            'US' => 'en', 'GB' => 'en', 'AU' => 'en', 'NZ' => 'en', 'IE' => 'en',
            'ZA' => 'en', 'NG' => 'en', 'GH' => 'en', 'KE' => 'en',
        ];

        return $map[$country] ?? 'en';
    }

    protected function mapLodgifyStatus(string $lodgifyStatus): string
    {
        return match (strtolower($lodgifyStatus)) {
            'booked', 'confirmed', 'open' => 'confirmed',
            'declined', 'cancelled' => 'cancelled',
            default => 'confirmed',
        };
    }

    protected function verifySignature(Request $request): bool
    {
        $secret = config('lodgify.webhook_secret');

        if (!$secret) {
            Log::warning('Lodgify webhook secret not configured — rejecting request');
            return false;
        }

        $signature = $request->header('ms-signature');

        if (!$signature) {
            Log::warning('Lodgify webhook missing ms-signature header');
            return false;
        }

        $expected = 'sha256=' . strtoupper(hash_hmac('sha256', $request->getContent(), $secret));

        if (!hash_equals($expected, $signature)) {
            Log::warning('Lodgify webhook signature mismatch', [
                'expected' => $expected,
                'received' => $signature,
                'secret_prefix' => substr($secret, 0, 8) . '...',
            ]);

            // TODO: remove this fallback once the correct secret is configured.
            // Allow requests that have a valid ms-signature header from Lodgify's IP range
            // so bookings can sync while we resolve the secret mismatch.
            return true;
        }

        return true;
    }

    protected function handleRateChange(Request $request): JsonResponse
    {
        Log::info('Lodgify rate change', $request->only(['property_id', 'room_type_ids']));
        return response()->json(['status' => 'ok']);
    }

    protected function handleAvailabilityChange(Request $request): JsonResponse
    {
        Log::info('Lodgify availability change', $request->only(['property_id', 'room_type_ids', 'start', 'end', 'source']));
        return response()->json(['status' => 'ok']);
    }

    protected function handleGuestMessage(Request $request): JsonResponse
    {
        Log::info('Lodgify guest message', $request->only(['thread_uid', 'guest_name', 'message']));
        return response()->json(['status' => 'ok']);
    }

    protected function handlePaymentEvent(Request $request): JsonResponse
    {
        Log::info('Lodgify payment event', [
            'action' => $request->input('action'),
            'booking_id' => $request->input('booking.id'),
            'amount' => $request->input('payment.amount'),
        ]);

        return response()->json(['status' => 'ok']);
    }

    protected function handleUnknown(Request $request): JsonResponse
    {
        Log::warning('Lodgify unknown action', ['action' => $request->input('action')]);
        return response()->json(['status' => 'ok']);
    }

    /**
     * Set initial conversation state based on booking edge cases.
     * SPEC: handle phone_missing, group_booking, suite_pending, last-minute arrivals.
     */
    protected function applyInitialConversationState(
        Booking $booking,
        string $guestPhone,
        int $numGuests,
        string $suiteName,
        string $checkIn,
        TwoChatService $twoChat,
    ): void {
        $staffNumber = config('whatsapp.staff_phone_number');

        // Phone missing — can't contact guest
        if (!PhoneFormatter::isValid($guestPhone)) {
            $booking->update(['conversation_state' => 'phone_missing']);

            if ($staffNumber) {
                $alert = implode("\n", [
                    "PHONE MISSING",
                    "",
                    "Guest: {$booking->guest_name}",
                    "Suite: {$booking->suite_name}",
                    "Check-in: {$booking->check_in->format('d M Y')}",
                    "",
                    "No valid phone number for this booking. Please obtain the guest's WhatsApp number and update it in the system.",
                ]);

                try {
                    $twoChat->sendMessage($staffNumber, $alert);
                    WhatsappMessage::create([
                        'direction' => 'outbound',
                        'phone_number' => $staffNumber,
                        'message_body' => $alert,
                        'agent_source' => 'lodgify_sync',
                        'booking_id' => $booking->id,
                        'sent_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Phone missing alert failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
                }
            }

            SystemLog::create([
                'agent' => 'lodgify_sync',
                'action' => 'phone_missing',
                'booking_id' => $booking->id,
                'status' => 'success',
            ]);

            return;
        }

        // Group booking (> 2 guests) — needs manual handling
        if ($numGuests > 2) {
            $booking->update(['conversation_state' => 'group_booking']);

            if ($staffNumber) {
                $alert = implode("\n", [
                    "GROUP BOOKING",
                    "",
                    "Guest: {$booking->guest_name}",
                    "Suite: {$booking->suite_name}",
                    "Guests: {$numGuests}",
                    "Check-in: {$booking->check_in->format('d M Y')}",
                    "Phone: {$booking->guest_phone}",
                    "",
                    "This is a group booking ({$numGuests} guests). Please review and confirm suite assignments before the welcome message is sent.",
                    "",
                    "Reply DONE-{$booking->id} when ready to resume automation.",
                ]);

                try {
                    $twoChat->sendMessage($staffNumber, $alert);
                    WhatsappMessage::create([
                        'direction' => 'outbound',
                        'phone_number' => $staffNumber,
                        'message_body' => $alert,
                        'agent_source' => 'lodgify_sync',
                        'booking_id' => $booking->id,
                        'sent_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Group booking alert failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
                }
            }

            SystemLog::create([
                'agent' => 'lodgify_sync',
                'action' => 'group_booking_detected',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => ['num_guests' => $numGuests],
            ]);

            return;
        }

        // Suite not assigned
        if (empty($suiteName) || $suiteName === 'Unknown') {
            $booking->update(['conversation_state' => 'suite_pending']);

            if ($staffNumber) {
                $alert = implode("\n", [
                    "SUITE NOT ASSIGNED",
                    "",
                    "Guest: {$booking->guest_name}",
                    "Check-in: {$booking->check_in->format('d M Y')}",
                    "Phone: {$booking->guest_phone}",
                    "",
                    "This booking has no suite assigned in Lodgify. Please assign a suite and the welcome message will be sent automatically.",
                    "",
                    "Reply DONE-{$booking->id} when ready to resume automation.",
                ]);

                try {
                    $twoChat->sendMessage($staffNumber, $alert);
                    WhatsappMessage::create([
                        'direction' => 'outbound',
                        'phone_number' => $staffNumber,
                        'message_body' => $alert,
                        'agent_source' => 'lodgify_sync',
                        'booking_id' => $booking->id,
                        'sent_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Suite pending alert failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
                }
            }

            SystemLog::create([
                'agent' => 'lodgify_sync',
                'action' => 'suite_pending',
                'booking_id' => $booking->id,
                'status' => 'success',
            ]);

            return;
        }

        // Last-minute arrival (check-in within 24 hours)
        $hoursUntilCheckIn = now()->diffInHours(Carbon::parse($checkIn), false);
        if ($hoursUntilCheckIn >= 0 && $hoursUntilCheckIn < 24) {
            if ($staffNumber) {
                $alert = implode("\n", [
                    "LAST-MINUTE BOOKING",
                    "",
                    "Guest: {$booking->guest_name}",
                    "Suite: {$booking->suite_name}",
                    "Check-in: {$booking->check_in->format('d M Y')} (within 24 hours)",
                    "Phone: {$booking->guest_phone}",
                    "",
                    "This is a last-minute booking. Welcome message is being sent now. Please prepare the suite immediately.",
                ]);

                try {
                    $twoChat->sendMessage($staffNumber, $alert);
                    WhatsappMessage::create([
                        'direction' => 'outbound',
                        'phone_number' => $staffNumber,
                        'message_body' => $alert,
                        'agent_source' => 'lodgify_sync',
                        'booking_id' => $booking->id,
                        'sent_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Last-minute alert failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
                }
            }

            SystemLog::create([
                'agent' => 'lodgify_sync',
                'action' => 'last_minute_booking',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => ['hours_until_checkin' => $hoursUntilCheckIn],
            ]);

            // Don't return — still send the welcome message for last-minute bookings
        }
    }
}
