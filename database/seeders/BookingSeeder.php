<?php

namespace Database\Seeders;

use App\Models\Booking;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $bookings = [
            ['lodgify_booking_id' => 'LDG-100001', 'guest_name' => 'Pierre Dupont', 'guest_phone' => '+33612345678', 'guest_email' => 'pierre@email.fr', 'guest_nationality' => 'French', 'num_guests' => 2, 'suite_name' => 'Suite Al Andalus', 'check_in' => '2026-06-10', 'check_out' => '2026-06-14', 'num_nights' => 4, 'booking_source' => 'Airbnb', 'booking_status' => 'confirmed', 'total_amount' => 8000.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100002', 'guest_name' => 'James Smith', 'guest_phone' => '+447700123456', 'guest_email' => 'james@email.co.uk', 'guest_nationality' => 'British', 'num_guests' => 2, 'suite_name' => 'Suite Zitoun', 'check_in' => '2026-06-15', 'check_out' => '2026-06-19', 'num_nights' => 4, 'booking_source' => 'Direct', 'booking_status' => 'confirmed', 'total_amount' => 7200.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100003', 'guest_name' => 'Maria García', 'guest_phone' => '+34612345678', 'guest_email' => 'maria@email.es', 'guest_nationality' => 'Spanish', 'num_guests' => 3, 'suite_name' => 'Suite Atlas', 'check_in' => '2026-06-20', 'check_out' => '2026-06-25', 'num_nights' => 5, 'booking_source' => 'Booking.com', 'booking_status' => 'confirmed', 'total_amount' => 10500.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100004', 'guest_name' => 'Hans Müller', 'guest_phone' => '+491511234567', 'guest_email' => 'hans@email.de', 'guest_nationality' => 'German', 'num_guests' => 2, 'suite_name' => 'Suite Menara', 'check_in' => '2026-06-28', 'check_out' => '2026-07-02', 'num_nights' => 4, 'booking_source' => 'Airbnb', 'booking_status' => 'confirmed', 'total_amount' => 7600.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100005', 'guest_name' => 'Sarah Johnson', 'guest_phone' => '+12025551234', 'guest_email' => 'sarah@email.com', 'guest_nationality' => 'American', 'num_guests' => 1, 'suite_name' => 'Suite Al Andalus', 'check_in' => '2026-07-05', 'check_out' => '2026-07-08', 'num_nights' => 3, 'booking_source' => 'Direct', 'booking_status' => 'confirmed', 'total_amount' => 5400.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100006', 'guest_name' => 'Fatima Benali', 'guest_phone' => '+212661234567', 'guest_email' => 'fatima@email.ma', 'guest_nationality' => 'Moroccan', 'num_guests' => 4, 'suite_name' => 'Suite Zitoun', 'check_in' => '2026-07-10', 'check_out' => '2026-07-15', 'num_nights' => 5, 'booking_source' => 'Direct', 'booking_status' => 'checked_in', 'total_amount' => 9500.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100007', 'guest_name' => 'Marco Rossi', 'guest_phone' => '+393331234567', 'guest_email' => 'marco@email.it', 'guest_nationality' => 'Italian', 'num_guests' => 2, 'suite_name' => 'Suite Atlas', 'check_in' => '2026-07-12', 'check_out' => '2026-07-16', 'num_nights' => 4, 'booking_source' => 'Airbnb', 'booking_status' => 'checked_in', 'total_amount' => 7200.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100008', 'guest_name' => 'Emma Wilson', 'guest_phone' => '+61412345678', 'guest_email' => 'emma@email.au', 'guest_nationality' => 'Australian', 'num_guests' => 2, 'suite_name' => 'Suite Menara', 'check_in' => '2026-07-14', 'check_out' => '2026-07-20', 'num_nights' => 6, 'booking_source' => 'Booking.com', 'booking_status' => 'checked_in', 'total_amount' => 12600.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100009', 'guest_name' => 'Jan de Vries', 'guest_phone' => '+31612345678', 'guest_email' => 'jan@email.nl', 'guest_nationality' => 'Dutch', 'num_guests' => 2, 'suite_name' => 'Suite Al Andalus', 'check_in' => '2026-07-20', 'check_out' => '2026-07-24', 'num_nights' => 4, 'booking_source' => 'Booking.com', 'booking_status' => 'checked_out', 'total_amount' => 7600.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100010', 'guest_name' => 'Sophie Martin', 'guest_phone' => '+33698765432', 'guest_email' => 'sophie@email.fr', 'guest_nationality' => 'French', 'num_guests' => 2, 'suite_name' => 'Suite Zitoun', 'check_in' => '2026-08-01', 'check_out' => '2026-08-05', 'num_nights' => 4, 'booking_source' => 'Airbnb', 'booking_status' => 'checked_out', 'total_amount' => 8400.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100011', 'guest_name' => 'Michael Brown', 'guest_phone' => '+14165551234', 'guest_email' => 'michael@email.ca', 'guest_nationality' => 'Canadian', 'num_guests' => 3, 'suite_name' => 'Suite Atlas', 'check_in' => '2026-08-05', 'check_out' => '2026-08-10', 'num_nights' => 5, 'booking_source' => 'Direct', 'booking_status' => 'cancelled', 'total_amount' => 9000.00, 'currency' => 'MAD'],
            ['lodgify_booking_id' => 'LDG-100012', 'guest_name' => 'Anna Schmidt', 'guest_phone' => '+491761234567', 'guest_email' => 'anna@email.de', 'guest_nationality' => 'German', 'num_guests' => 2, 'suite_name' => 'Suite Menara', 'check_in' => '2026-08-12', 'check_out' => '2026-08-15', 'num_nights' => 3, 'booking_source' => 'Booking.com', 'booking_status' => 'cancelled', 'total_amount' => 5100.00, 'currency' => 'MAD'],
        ];

        Booking::insert($bookings);
    }
}
