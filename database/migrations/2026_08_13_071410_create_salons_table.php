<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('business_name');
            $table->string('location');
            $table->string('google_place_id')->nullable();
            $table->string('website')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('logo_url')->nullable();
            $table->enum('subscription_status', ['trialing', 'active', 'overdue', 'cancelled'])->default('trialing');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salons');
    }
};
