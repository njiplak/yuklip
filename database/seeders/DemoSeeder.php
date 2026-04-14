<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\UpsellLog;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();

        $customers = $this->seedCustomers();
        $offers = Offer::all()->keyBy('offer_code');

        $this->seedMarchData($customers, $offers);
        $this->seedAprilData($customers, $offers);
        $this->seedCancellations($customers);
        $this->seedEscalations($customers);
    }

    protected function seedSettings(): void
    {
        Setting::firstOrCreate(['key' => 'currency'], ['value' => 'EUR']);
        Setting::firstOrCreate(['key' => 'concierge_fee_rate'], ['value' => '15']);
    }

    protected function seedCustomers(): array
    {
        $data = [
            ['phone' => '+33612345678', 'name' => 'Pierre Dupont', 'email' => 'pierre.dupont@mail.fr', 'nationality' => 'French', 'language' => 'fr', 'total_stays' => 2],
            ['phone' => '+44789012345', 'name' => 'Sarah Mitchell', 'email' => 'sarah.m@mail.co.uk', 'nationality' => 'British', 'language' => 'en', 'total_stays' => 1],
            ['phone' => '+49170123456', 'name' => 'James Weber', 'email' => 'j.weber@mail.de', 'nationality' => 'German', 'language' => 'en', 'total_stays' => 1],
            ['phone' => '+31620123456', 'name' => 'Lisa Kuijpers', 'email' => 'lisa.k@mail.nl', 'nationality' => 'Dutch', 'language' => 'en', 'total_stays' => 1],
            ['phone' => '+34612345678', 'name' => 'Ahmed Benali', 'email' => 'ahmed.b@mail.ma', 'nationality' => 'Moroccan', 'language' => 'fr', 'total_stays' => 1],
            ['phone' => '+39321654987', 'name' => 'Sophie Laurent', 'email' => 'sophie.l@mail.fr', 'nationality' => 'French', 'language' => 'fr', 'total_stays' => 3],
            ['phone' => '+1415555012', 'name' => 'Maria Chen', 'email' => 'maria.c@mail.com', 'nationality' => 'American', 'language' => 'en', 'total_stays' => 1],
            ['phone' => '+61412345678', 'name' => 'Oliver Brown', 'email' => 'oliver.b@mail.au', 'nationality' => 'Australian', 'language' => 'en', 'total_stays' => 1],
            ['phone' => '+33698765432', 'name' => 'Claire Martin', 'email' => 'claire.m@mail.fr', 'nationality' => 'French', 'language' => 'fr', 'total_stays' => 2],
            ['phone' => '+4917012340', 'name' => 'Hans Müller', 'email' => 'hans.m@mail.de', 'nationality' => 'German', 'language' => 'de', 'total_stays' => 1],
        ];

        $customers = [];
        foreach ($data as $row) {
            $customers[$row['name']] = Customer::create(array_merge($row, [
                'first_stay_at' => '2025-06-01',
                'last_stay_at' => '2026-04-01',
            ]));
        }

        return $customers;
    }

    // ---------------------------------------------------------------
    // March 2026 — past month data for comparison
    // ---------------------------------------------------------------
    protected function seedMarchData(array $customers, $offers): void
    {
        $marchBookings = [
            ['customer' => 'Oliver Brown', 'suite' => 'Suite Atlas', 'in' => '2026-03-02', 'out' => '2026-03-06', 'nights' => 4, 'amount' => 2400, 'source' => 'Airbnb', 'status' => 'checked_out'],
            ['customer' => 'Claire Martin', 'suite' => 'Suite Menara', 'in' => '2026-03-08', 'out' => '2026-03-13', 'nights' => 5, 'amount' => 3250, 'source' => 'Direct', 'status' => 'checked_out'],
            ['customer' => 'Hans Müller', 'suite' => 'Suite Zitoun', 'in' => '2026-03-14', 'out' => '2026-03-18', 'nights' => 4, 'amount' => 2200, 'source' => 'Booking.com', 'status' => 'checked_out'],
            ['customer' => 'Pierre Dupont', 'suite' => 'Suite Al Andalus', 'in' => '2026-03-20', 'out' => '2026-03-25', 'nights' => 5, 'amount' => 2750, 'source' => 'Direct', 'status' => 'checked_out'],
        ];

        foreach ($marchBookings as $row) {
            $customer = $customers[$row['customer']];
            $booking = $this->createBooking($customer, $row);
            $this->createAccommodationTransaction($booking, $row['in']);
        }

        // March expenses
        $this->createExpenses('2026-03', [
            ['Cleaning services', 420],
            ['Linen replacement', 280],
            ['Utilities — electricity & water', 210],
            ['Supplies — toiletries & consumables', 130],
        ]);

        // March upsell logs
        $this->createUpsellLog($marchBookings, $customers, $offers, '2026-03');
    }

    // ---------------------------------------------------------------
    // April 2026 — current month, matches mockup numbers:
    //   Accommodation 10,500 | Upsells 2,310 | Expenses 1,200
    //   Fee 15% → 1,921.50 | Net profit 9,688.50
    // ---------------------------------------------------------------
    protected function seedAprilData(array $customers, $offers): void
    {
        $aprilBookings = [
            ['customer' => 'Pierre Dupont', 'suite' => 'Suite Atlas', 'in' => '2026-04-01', 'out' => '2026-04-05', 'nights' => 4, 'amount' => 2800, 'source' => 'Direct', 'status' => 'checked_out'],
            ['customer' => 'Sarah Mitchell', 'suite' => 'Suite Menara', 'in' => '2026-04-03', 'out' => '2026-04-08', 'nights' => 5, 'amount' => 3500, 'source' => 'Airbnb', 'status' => 'checked_out'],
            ['customer' => 'James Weber', 'suite' => 'Suite Zitoun', 'in' => '2026-04-06', 'out' => '2026-04-09', 'nights' => 3, 'amount' => 2100, 'source' => 'Booking.com', 'status' => 'checked_out'],
            ['customer' => 'Lisa Kuijpers', 'suite' => 'Suite Al Andalus', 'in' => '2026-04-08', 'out' => '2026-04-12', 'nights' => 4, 'amount' => 2100, 'source' => 'Direct', 'status' => 'checked_out'],
        ];

        foreach ($aprilBookings as $row) {
            $customer = $customers[$row['customer']];
            $booking = $this->createBooking($customer, $row);
            $this->createAccommodationTransaction($booking, $row['in']);
        }

        // Upsell income that totals 2,310 EUR
        $upsellTransactions = [
            ['booking_idx' => 0, 'desc' => 'Rooftop dinner for two', 'amount' => 960, 'date' => '2026-04-02'],
            ['booking_idx' => 1, 'desc' => 'Traditional hammam & gommage', 'amount' => 640, 'date' => '2026-04-05'],
            ['booking_idx' => 2, 'desc' => 'Medina guided walking tour', 'amount' => 400, 'date' => '2026-04-07'],
            ['booking_idx' => 3, 'desc' => 'Moroccan cooking class', 'amount' => 310, 'date' => '2026-04-10'],
        ];

        // We need to look up the bookings we just created
        $createdBookings = Booking::whereIn('suite_name', ['Suite Atlas', 'Suite Menara', 'Suite Zitoun', 'Suite Al Andalus'])
            ->where('check_in', '>=', '2026-04-01')
            ->where('check_in', '<=', '2026-04-12')
            ->where('booking_status', '!=', 'cancelled')
            ->orderBy('check_in')
            ->get();

        foreach ($upsellTransactions as $ut) {
            if (isset($createdBookings[$ut['booking_idx']])) {
                Transaction::create([
                    'booking_id' => $createdBookings[$ut['booking_idx']]->id,
                    'type' => 'income',
                    'category' => 'upsell',
                    'description' => $ut['desc'],
                    'amount' => $ut['amount'],
                    'currency' => 'EUR',
                    'transaction_date' => $ut['date'],
                    'recorded_by' => 'yasmine',
                ]);
            }
        }

        // April expenses totaling 1,200 EUR
        $this->createExpenses('2026-04', [
            ['Cleaning services', 480],
            ['Plumbing repair — Suite Zitoun', 320],
            ['Utilities — electricity & water', 250],
            ['Supplies — toiletries & consumables', 150],
        ]);

        // Upsell logs for April bookings
        $this->seedAprilUpsellLogs($createdBookings, $offers);

        // System logs for successful agent actions
        foreach ($createdBookings as $booking) {
            SystemLog::create([
                'agent' => 'concierge',
                'action' => 'welcome_sent',
                'booking_id' => $booking->id,
                'status' => 'success',
                'duration_ms' => rand(800, 2500),
                'created_at' => Carbon::parse($booking->check_in)->setHour(14),
                'updated_at' => Carbon::parse($booking->check_in)->setHour(14),
            ]);

            SystemLog::create([
                'agent' => 'preference_extractor',
                'action' => 'preferences_extracted',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => ['preferences' => ['bed_type' => 'king', 'arrival_time' => '15:00']],
                'duration_ms' => rand(1200, 3000),
                'created_at' => Carbon::parse($booking->check_in)->setHour(15),
                'updated_at' => Carbon::parse($booking->check_in)->setHour(15),
            ]);
        }

        // WhatsApp message samples for the most recent booking
        if ($createdBookings->isNotEmpty()) {
            $lastBooking = $createdBookings->last();
            $this->seedWhatsappMessages($lastBooking);
        }
    }

    // ---------------------------------------------------------------
    // Cancellations — recent, visible on Alerts page
    // ---------------------------------------------------------------
    protected function seedCancellations(array $customers): void
    {
        // Ahmed B. — cancelled yesterday
        $ahmed = $customers['Ahmed Benali'];
        $cancelledBooking1 = $this->createBooking($ahmed, [
            'suite' => 'Suite Atlas',
            'in' => '2026-04-12',
            'out' => '2026-04-15',
            'nights' => 3,
            'amount' => 480,
            'source' => 'Airbnb',
            'status' => 'cancelled',
        ]);
        $cancelledBooking1->update([
            'updated_at' => now()->subHours(6),
        ]);

        SystemLog::create([
            'agent' => 'cancellation_recovery',
            'action' => 'recovery_plan_sent',
            'booking_id' => $cancelledBooking1->id,
            'status' => 'success',
            'duration_ms' => 4200,
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        // Maria Chen — cancelled 2 days ago
        $maria = $customers['Maria Chen'];
        $cancelledBooking2 = $this->createBooking($maria, [
            'suite' => 'Suite Menara',
            'in' => '2026-04-14',
            'out' => '2026-04-17',
            'nights' => 3,
            'amount' => 510,
            'source' => 'Booking.com',
            'status' => 'cancelled',
        ]);
        $cancelledBooking2->update([
            'updated_at' => now()->subDays(2),
        ]);
    }

    // ---------------------------------------------------------------
    // Escalations — recent, visible on Alerts page
    // ---------------------------------------------------------------
    protected function seedEscalations(array $customers): void
    {
        // Sophie L. — sentiment escalation for out-of-catalogue request
        $sophie = $customers['Sophie Laurent'];
        $sophieBooking = $this->createBooking($sophie, [
            'suite' => 'Suite Menara',
            'in' => '2026-04-11',
            'out' => '2026-04-16',
            'nights' => 5,
            'amount' => 3200,
            'source' => 'Direct',
            'status' => 'checked_in',
        ]);
        $sophieBooking->update(['conversation_state' => 'handover_human']);

        SystemLog::create([
            'agent' => 'preference_extractor',
            'action' => 'sentiment_escalated',
            'booking_id' => $sophieBooking->id,
            'status' => 'success',
            'payload' => [
                'sentiment' => 'handover_human',
                'reason' => 'Yasmine escalated — private rooftop dinner for 12 people. Out of catalogue.',
            ],
            'duration_ms' => 1800,
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        // Another escalation — guest not responding
        $hans = $customers['Hans Müller'];
        $hansBooking = Booking::where('customer_id', $hans->id)
            ->where('booking_status', '!=', 'cancelled')
            ->first();

        if ($hansBooking) {
            SystemLog::create([
                'agent' => 'follow_up',
                'action' => 'escalated_to_manager',
                'booking_id' => $hansBooking->id,
                'status' => 'success',
                'duration_ms' => 950,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ]);
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------
    protected function createBooking(Customer $customer, array $data): Booking
    {
        return Booking::create([
            'customer_id' => $customer->id,
            'lodgify_booking_id' => 'LDG-' . rand(100000, 999999),
            'guest_name' => $customer->name,
            'guest_phone' => $customer->phone,
            'guest_email' => $customer->email,
            'guest_nationality' => $customer->nationality,
            'detected_language' => $customer->language,
            'num_guests' => rand(1, 3),
            'suite_name' => $data['suite'],
            'check_in' => $data['in'],
            'check_out' => $data['out'],
            'num_nights' => $data['nights'],
            'booking_source' => $data['source'],
            'booking_status' => $data['status'],
            'total_amount' => $data['amount'],
            'currency' => 'EUR',
            'conversation_state' => match ($data['status']) {
                'checked_in' => 'active',
                'checked_out' => 'completed',
                'cancelled' => 'cancelled',
                default => 'pre_arrival',
            },
        ]);
    }

    protected function createAccommodationTransaction(Booking $booking, string $date): void
    {
        Transaction::create([
            'booking_id' => $booking->id,
            'type' => 'income',
            'category' => 'accommodation',
            'description' => "{$booking->suite_name} — {$booking->guest_name} ({$booking->num_nights}N)",
            'amount' => $booking->total_amount,
            'currency' => 'EUR',
            'transaction_date' => $date,
            'payment_method' => $booking->booking_source === 'Direct' ? 'bank_transfer' : 'platform',
            'recorded_by' => 'yasmine',
        ]);
    }

    protected function createExpenses(string $yearMonth, array $items): void
    {
        foreach ($items as $idx => [$desc, $amount]) {
            Transaction::create([
                'type' => 'expense',
                'category' => 'operational',
                'description' => $desc,
                'amount' => $amount,
                'currency' => 'EUR',
                'transaction_date' => "{$yearMonth}-" . str_pad(5 + $idx * 7, 2, '0', STR_PAD_LEFT),
                'recorded_by' => 'manager',
            ]);
        }
    }

    protected function createUpsellLog(array $bookings, array $customers, $offers, string $month): void
    {
        $offerCodes = ['HAMMAM_D2', 'ROOFTOP_DINNER_D2', 'MEDINA_TOUR_D3', 'COOKING_CLASS_D4'];
        $outcomes = ['accepted', 'accepted', 'declined', 'no_reply'];

        foreach ($bookings as $idx => $row) {
            if (!isset($offerCodes[$idx])) {
                continue;
            }

            $offer = $offers->get($offerCodes[$idx]);
            if (!$offer) {
                continue;
            }

            $customer = $customers[$row['customer']];
            $booking = Booking::where('customer_id', $customer->id)
                ->where('check_in', $row['in'])
                ->first();

            if (!$booking) {
                continue;
            }

            $outcome = $outcomes[$idx];
            $sentAt = Carbon::parse($row['in'])->addDays(1)->setHour(10);

            UpsellLog::create([
                'booking_id' => $booking->id,
                'offer_id' => $offer->id,
                'message_sent' => "Hi {$customer->name}! We'd love to offer you our {$offer->title}. {$offer->description} Price: {$offer->price} {$offer->currency}. Interested?",
                'sent_at' => $sentAt,
                'guest_reply' => $outcome === 'no_reply' ? null : ($outcome === 'accepted' ? 'Yes please, that sounds wonderful!' : 'No thank you, not this time.'),
                'reply_received_at' => $outcome === 'no_reply' ? null : $sentAt->copy()->addHours(rand(1, 4)),
                'outcome' => $outcome,
                'revenue_generated' => $outcome === 'accepted' ? $offer->price : null,
            ]);
        }
    }

    protected function seedAprilUpsellLogs($bookings, $offers): void
    {
        $mapping = [
            ['offer' => 'ROOFTOP_DINNER_D2', 'outcome' => 'accepted', 'revenue' => 960],
            ['offer' => 'HAMMAM_D2', 'outcome' => 'accepted', 'revenue' => 640],
            ['offer' => 'MEDINA_TOUR_D3', 'outcome' => 'accepted', 'revenue' => 400],
            ['offer' => 'COOKING_CLASS_D4', 'outcome' => 'accepted', 'revenue' => 310],
        ];

        foreach ($bookings as $idx => $booking) {
            if (!isset($mapping[$idx])) {
                continue;
            }

            $m = $mapping[$idx];
            $offer = $offers->get($m['offer']);

            if (!$offer) {
                continue;
            }

            $sentAt = Carbon::parse($booking->check_in)->addDays(1)->setHour(10);

            UpsellLog::create([
                'booking_id' => $booking->id,
                'offer_id' => $offer->id,
                'message_sent' => "Hi {$booking->guest_name}! We'd love to offer you our {$offer->title}. Price: {$m['revenue']} EUR. Interested?",
                'sent_at' => $sentAt,
                'guest_reply' => 'Yes, that sounds great!',
                'reply_received_at' => $sentAt->copy()->addHours(2),
                'outcome' => $m['outcome'],
                'revenue_generated' => $m['revenue'],
            ]);
        }

        // A couple declined/pending for realism
        if ($bookings->count() >= 2) {
            $offer = $offers->get('LATE_CHECKOUT');
            if ($offer) {
                $booking = $bookings[1];
                UpsellLog::create([
                    'booking_id' => $booking->id,
                    'offer_id' => $offer->id,
                    'message_sent' => "Hi {$booking->guest_name}! Would you like a late checkout until 14:00?",
                    'sent_at' => Carbon::parse($booking->check_out)->subDays(1)->setHour(9),
                    'guest_reply' => 'No thanks, we have an early flight.',
                    'reply_received_at' => Carbon::parse($booking->check_out)->subDays(1)->setHour(10),
                    'outcome' => 'declined',
                    'revenue_generated' => null,
                ]);
            }

            $offer = $offers->get('AIRPORT_TRANSFER');
            if ($offer && $bookings->count() >= 3) {
                $booking = $bookings[2];
                UpsellLog::create([
                    'booking_id' => $booking->id,
                    'offer_id' => $offer->id,
                    'message_sent' => "Hi {$booking->guest_name}! Need a private airport transfer?",
                    'sent_at' => Carbon::parse($booking->check_out)->subDays(1)->setHour(9),
                    'outcome' => 'pending',
                ]);
            }
        }
    }

    protected function seedWhatsappMessages(Booking $booking): void
    {
        $checkIn = Carbon::parse($booking->check_in);
        $messages = [
            ['dir' => 'outbound', 'agent' => 'concierge', 'body' => "Hello {$booking->guest_name}! Welcome to Yasmine.ai. I'm your virtual concierge for your upcoming stay at {$booking->suite_name}. How can I help you prepare for your arrival?", 'time' => $checkIn->copy()->subDays(2)->setHour(10)],
            ['dir' => 'inbound', 'agent' => null, 'body' => "Hi! Thanks. What time is check-in? And is parking available?", 'time' => $checkIn->copy()->subDays(2)->setHour(11)],
            ['dir' => 'outbound', 'agent' => 'concierge', 'body' => "Check-in is from 15:00 onwards. We don't have on-site parking, but there's a secure car park 2 minutes walk away (50 MAD/night). I can reserve a spot for you if you'd like!", 'time' => $checkIn->copy()->subDays(2)->setHour(11)->addMinutes(3)],
            ['dir' => 'inbound', 'agent' => null, 'body' => "Perfect, yes please reserve parking. We'll arrive around 16:00.", 'time' => $checkIn->copy()->subDays(2)->setHour(12)],
            ['dir' => 'outbound', 'agent' => 'concierge', 'body' => "Done! Parking reserved. See you at 16:00. If you need anything before arrival, don't hesitate to ask.", 'time' => $checkIn->copy()->subDays(2)->setHour(12)->addMinutes(2)],
            ['dir' => 'outbound', 'agent' => 'concierge', 'body' => "Welcome! Your suite is ready. WiFi: RiadYasmine / Password: welcome2026. Breakfast is served 8-10am on the terrace. Enjoy your stay!", 'time' => $checkIn->copy()->setHour(15)],
            ['dir' => 'inbound', 'agent' => null, 'body' => "Thank you so much! The suite is beautiful.", 'time' => $checkIn->copy()->setHour(17)],
        ];

        foreach ($messages as $msg) {
            WhatsappMessage::create([
                'booking_id' => $booking->id,
                'direction' => $msg['dir'],
                'phone_number' => $booking->guest_phone,
                'message_body' => $msg['body'],
                'agent_source' => $msg['agent'],
                'sent_at' => $msg['dir'] === 'outbound' ? $msg['time'] : null,
                'received_at' => $msg['dir'] === 'inbound' ? $msg['time'] : null,
            ]);
        }
    }
}
