<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AddonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomElement([5, 10, 20]),
            'is_active' => true,
        ];
    }
}
