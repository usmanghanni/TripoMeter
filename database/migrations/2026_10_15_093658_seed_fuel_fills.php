<?php

use Database\Seeders\FuelFillSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * On-device seeding: NativePHP runs migrations (never db:seed) on app start.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Keep test databases empty; FuelFillSeederTest covers the seeder itself.
        if (app()->runningUnitTests()) {
            return;
        }

        (new FuelFillSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
