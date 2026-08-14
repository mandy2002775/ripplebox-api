<?php

namespace Database\Factories;

use App\Enums\RecipientType;
use App\Enums\RewardType;
use App\Models\Reward;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'reward_type' => RewardType::GiftCard,
            'reward_value' => 50,
            'description' => '$50 gift card for you and your friend',
            'recipient_type' => RecipientType::Both,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_active' => true,
        ];
    }
}
