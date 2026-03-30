<?php

namespace Database\Factories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        return [
            'offer_code' => strtoupper(fake()->unique()->word()) . '_' . fake()->randomElement(['D1', 'D2', 'D3']),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['wellness', 'dining', 'experience', 'transport']),
            'timing_rule' => fake()->randomElement(['arrival_day', 'day_2', 'day_3', 'day_4', 'day_1_before_checkout']),
            'price' => fake()->numberBetween(150, 1500),
            'currency' => 'MAD',
            'is_active' => true,
        ];
    }
}
