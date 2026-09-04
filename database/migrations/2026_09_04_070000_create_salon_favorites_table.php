<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('salon_id')->constrained('salons')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['client_id', 'salon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_favorites');
    }
};
