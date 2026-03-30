<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    protected array $suites = [
        'Suite Al Andalus',
        'Suite Zitoun',
        'Suite Atlas',
        'Suite Menara',
    ];

    protected array $nationalities = [
        'French', 'British', 'American', 'German', 'Spanish',
        'Italian', 'Dutch', 'Moroccan', 'Canadian', 'Australian',
    ];

    protected array $sources = ['Airbnb', 'Direct', 'Booking.com'];

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('2026-06-01', '2026-08-15');
        $nights = fake()->numberBetween(2, 7);
        $checkOut = (clone $checkIn)->modify("+{$nights} days");

        return [
            'lodgify_booking_id' => 'LDG-' . fake()->unique()->numerify('######'),
            'guest_name' => fake()->name(),
            'guest_phone' => fake()->e164PhoneNumber(),
            'guest_email' => fake()->safeEmail(),
            'guest_nationality' => fake()->randomElement($this->nationalities),
            'num_guests' => fake()->numberBetween(1, 4),
            'suite_name' => fake()->randomElement($this->suites),
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => $checkOut->format('Y-m-d'),
            'num_nights' => $nights,
            'booking_source' => fake()->randomElement($this->sources),
            'booking_status' => 'confirmed',
            'total_amount' => fake()->numberBetween(3000, 15000),
            'currency' => 'MAD',
            'special_requests' => fake()->optional(0.4)->sentence(),
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => ['booking_status' => 'checked_in']);
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => ['booking_status' => 'checked_out']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['booking_status' => 'cancelled']);
    }
}
