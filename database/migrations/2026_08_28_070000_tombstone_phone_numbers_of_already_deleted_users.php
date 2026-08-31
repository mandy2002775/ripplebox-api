<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill for the phone-number-reuse bug fixed in AccountController::destroy():
 * any user already soft-deleted before that fix still holds their real
 * phone_number under the unique constraint, which would 500 the moment
 * that number tried to sign up again. One-time cleanup, not a recurring
 * migration concern — new deletions are tombstoned at delete time now.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->where('phone_number', 'not like', '%#deleted#%')
            ->orderBy('id')
            ->get(['id', 'phone_number'])
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['phone_number' => "{$user->phone_number}#deleted#{$user->id}"]);
            });
    }

    public function down(): void
    {
        // Not reversible — the original phone number isn't recoverable from
        // the tombstoned value alone once other rows may have taken it.
    }
};
