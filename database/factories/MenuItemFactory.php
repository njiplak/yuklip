<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'name_fr' => fake()->optional()->words(3, true),
            'category' => fake()->randomElement(['breakfast', 'lunch', 'dinner', 'drinks', 'snacks']),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->optional()->numberBetween(20, 300),
            'currency' => 'MAD',
            'is_available' => true,
            'availability_note' => null,
        ];
    }
}
