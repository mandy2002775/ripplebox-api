<?php

namespace Database\Factories;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salon>
 */
class SalonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->salon(),
            'business_name' => fake()->company(),
            'location' => fake()->streetAddress(),
        ];
    }
}
