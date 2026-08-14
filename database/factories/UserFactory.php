<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number' => '+614'.fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'user_type' => UserType::Client,
        ];
    }

    public function salon(): static
    {
        return $this->state(fn () => ['user_type' => UserType::Salon]);
    }
}
