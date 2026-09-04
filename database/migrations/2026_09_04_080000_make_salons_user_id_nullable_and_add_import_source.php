<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A salon can now exist without an owner account — an imported, real
     * business listing (see App\Console\Commands\ImportSalonsFromOsm) that
     * nobody has claimed yet. `external_ref` lets a re-run of the import
     * upsert instead of duplicating; `source` distinguishes an owner's own
     * sign-up from an import for admin visibility.
     */
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->change();
            $table->string('source')->default('signup')->after('user_id');
            $table->string('external_ref')->nullable()->unique()->after('google_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropUnique(['external_ref']);
            $table->dropColumn(['source', 'external_ref']);
            $table->foreignUuid('user_id')->nullable(false)->change();
        });
    }
};
