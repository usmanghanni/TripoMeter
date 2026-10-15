<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The personal speed limit was stored in whatever unit was active; store it in
 * km/h only so switching units converts it instead of reinterpreting it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('speed_limit', 'speed_limit_kmh');
        });

        DB::table('settings')->where('unit', 'mph')->update([
            'speed_limit_kmh' => DB::raw('CAST(ROUND(speed_limit_kmh * 1.609344) AS INTEGER)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('unit', 'mph')->update([
            'speed_limit_kmh' => DB::raw('CAST(ROUND(speed_limit_kmh / 1.609344) AS INTEGER)'),
        ]);

        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('speed_limit_kmh', 'speed_limit');
        });
    }
};
