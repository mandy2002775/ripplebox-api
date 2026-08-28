<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('salon_id')->constrained('salons')->restrictOnDelete();
            $table->string('image_path');
            $table->string('image_mime');
            $table->string('caption')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_posts');
    }
};
