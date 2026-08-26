<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes three race windows a concurrency audit found: two simultaneous
 * requests could otherwise create two salons for one user, redeem the same
 * referral's reward twice, or create two referrals for the same
 * referrer/referred/salon triple. The application code already checks for
 * these before creating a row, but a check-then-act without a DB-level
 * constraint doesn't stop two requests that both pass the check
 * simultaneously — the constraint is the actual guarantee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->unique('user_id');
        });

        Schema::table('redemptions', function (Blueprint $table) {
            $table->unique('referral_id');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->unique(['referrer_client_id', 'referred_client_id', 'salon_id'], 'referrals_unique_triple');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        Schema::table('redemptions', function (Blueprint $table) {
            $table->dropUnique(['referral_id']);
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropUnique('referrals_unique_triple');
        });
    }
};
