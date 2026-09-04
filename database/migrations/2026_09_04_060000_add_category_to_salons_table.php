<?php

use App\Enums\SalonCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Client-requested: Discover has no way to browse salons by the kind of
// service they offer — every real beauty-discovery app (including the
// team's prior build) treats category as a first-class filter. Nullable so
// existing salons aren't forced to pick one immediately.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->enum('category', array_column(SalonCategory::cases(), 'value'))
                ->nullable()
                ->after('business_name');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
