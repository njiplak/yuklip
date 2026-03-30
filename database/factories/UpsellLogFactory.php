<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Offer;
use App\Models\UpsellLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UpsellLog>
 */
class UpsellLogFactory extends Factory
{
    protected $model = UpsellLog::class;

    public function definition(): array
    {
        $outcome = fake()->randomElement(['accepted', 'declined', 'no_reply', 'pending']);

        return [
            'booking_id' => Booking::factory(),
            'offer_id' => Offer::factory(),
            'message_sent' => fake()->paragraph(),
            'sent_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'guest_reply' => $outcome !== 'no_reply' ? fake()->sentence() : null,
            'reply_received_at' => $outcome !== 'no_reply' ? fake()->dateTimeBetween('-3 days', 'now') : null,
            'outcome' => $outcome,
            'revenue_generated' => $outcome === 'accepted' ? fake()->numberBetween(200, 1200) : null,
        ];
    }
}
