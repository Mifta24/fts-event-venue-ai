<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What sets an apartment apart from a hotel room: a unit layout (bedrooms,
 * bathrooms, which floors it sits on) and cheaper rates for longer stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('bedrooms')->default(1)->after('size_sqm');
            $table->unsignedTinyInteger('bathrooms')->default(1)->after('bedrooms');
            $table->string('floor_range', 20)->nullable()->after('bathrooms');
        });

        Schema::table('apartments', function (Blueprint $table) {
            $table->unsignedTinyInteger('weekly_discount_percent')->default(0)->after('check_out_time');
            $table->unsignedTinyInteger('monthly_discount_percent')->default(0)->after('weekly_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->dropColumn(['bedrooms', 'bathrooms', 'floor_range']);
        });

        Schema::table('apartments', function (Blueprint $table) {
            $table->dropColumn(['weekly_discount_percent', 'monthly_discount_percent']);
        });
    }
};
