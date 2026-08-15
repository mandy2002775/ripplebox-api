<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. There's no public "sign up as
     * admin" screen — admin accounts are provisioned here instead. Log in
     * with this phone number via the normal OTP flow (any role tile on
     * role-select works, since an existing user's actual role is what
     * comes back, not whichever tile was tapped).
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Kate Dawes',
            'phone_number' => '+61400000001',
            'user_type' => UserType::Admin,
        ]);
    }
}
