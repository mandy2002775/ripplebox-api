<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('referred_client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('salon_id')->constrained('salons')->restrictOnDelete();
            $table->enum('status', ['pending', 'engaged', 'redeemed'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        // Defense in depth alongside the application-level check in
        // ReferralController::store() — a referral can never name the same
        // client as both referrer and referred. SQLite's ALTER TABLE has no
        // ADD CONSTRAINT support (dev/test run on SQLite), so this only
        // applies against the production MySQL target.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE referrals ADD CONSTRAINT referrer_not_referred CHECK (referrer_client_id != referred_client_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
