<?php

namespace Database\Factories;

use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppRelease>
 */
class AppReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['platform' => 'android', 'version_name' => '1.0.0', 'version_code' => fake()->unique()->numberBetween(1, 999999), 'is_published' => false, 'is_current' => false, 'uploaded_by' => User::factory()];
    }
}
