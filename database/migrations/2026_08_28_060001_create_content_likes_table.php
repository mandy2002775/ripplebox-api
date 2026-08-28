<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_likes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_post_id')->constrained('content_posts')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['content_post_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_likes');
    }
};
