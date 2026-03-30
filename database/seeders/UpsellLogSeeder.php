<?php

namespace Database\Seeders;

use App\Models\UpsellLog;
use Illuminate\Database\Seeder;

class UpsellLogSeeder extends Seeder
{
    public function run(): void
    {
        // Booking IDs 6,7,8 are checked_in. Offer IDs 1-8 from OfferSeeder.
        $logs = [
            ['booking_id' => 6, 'offer_id' => 1, 'message_sent' => 'Bonjour Fatima! Welcome to Riad Larbi Khalis. Would you like a refreshing welcome drink with a traditional date platter served in your suite?', 'sent_at' => '2026-07-10 15:00:00', 'guest_reply' => 'Oui merci, avec plaisir!', 'reply_received_at' => '2026-07-10 15:12:00', 'outcome' => 'accepted', 'revenue_generated' => 150.00],
            ['booking_id' => 6, 'offer_id' => 2, 'message_sent' => 'Fatima, day 2 at the riad! How about a traditional hammam & gommage for two at a nearby luxury spa?', 'sent_at' => '2026-07-11 09:00:00', 'guest_reply' => 'Not today, thank you.', 'reply_received_at' => '2026-07-11 10:30:00', 'outcome' => 'declined', 'revenue_generated' => null],
            ['booking_id' => 7, 'offer_id' => 1, 'message_sent' => 'Benvenuto Marco! Welcome to Riad Larbi Khalis. Can we bring you a welcome drink and date platter to your suite?', 'sent_at' => '2026-07-12 14:00:00', 'guest_reply' => 'Si grazie!', 'reply_received_at' => '2026-07-12 14:08:00', 'outcome' => 'accepted', 'revenue_generated' => 150.00],
            ['booking_id' => 7, 'offer_id' => 3, 'message_sent' => 'Marco, would you enjoy a private rooftop dinner under the stars tonight? Multi-course Moroccan menu with candles and lanterns.', 'sent_at' => '2026-07-13 09:00:00', 'guest_reply' => null, 'reply_received_at' => null, 'outcome' => 'no_reply', 'revenue_generated' => null],
            ['booking_id' => 8, 'offer_id' => 1, 'message_sent' => 'Welcome Emma! Would you like a welcome drink and date platter delivered to Suite Menara?', 'sent_at' => '2026-07-14 16:00:00', 'guest_reply' => 'Yes please! That sounds lovely.', 'reply_received_at' => '2026-07-14 16:05:00', 'outcome' => 'accepted', 'revenue_generated' => 150.00],
            ['booking_id' => 8, 'offer_id' => 2, 'message_sent' => 'Emma, fancy a traditional hammam and gommage for two? Perfect way to unwind on your second day!', 'sent_at' => '2026-07-15 09:00:00', 'guest_reply' => null, 'reply_received_at' => null, 'outcome' => 'pending', 'revenue_generated' => null],
        ];

        UpsellLog::insert($logs);
    }
}
