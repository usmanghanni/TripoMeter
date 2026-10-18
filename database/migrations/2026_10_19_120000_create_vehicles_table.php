<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing data is kept: everything already recorded goes to a "My car"
     * vehicle that takes over the tank size and oil interval from settings.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->double('tank_capacity_l')->nullable();
            $table->unsignedInteger('oil_change_interval_km')->default(5000);
            $table->unsignedTinyInteger('oil_change_interval_months')->default(6);
            $table->timestamps();
        });

        foreach (['trips', 'fuel_fills', 'expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedBigInteger('active_vehicle_id')->nullable();
        });

        $settings = DB::table('settings')->first();

        $vehicleId = DB::table('vehicles')->insertGetId([
            'name' => 'My car',
            'tank_capacity_l' => $settings->tank_capacity_l ?? null,
            'oil_change_interval_km' => $settings->oil_change_interval_km ?? 5000,
            'oil_change_interval_months' => $settings->oil_change_interval_months ?? 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['trips', 'fuel_fills', 'expenses'] as $tableName) {
            DB::table($tableName)->update(['vehicle_id' => $vehicleId]);
        }

        DB::table('settings')->update(['active_vehicle_id' => $vehicleId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['trips', 'fuel_fills', 'expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('vehicle_id');
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('active_vehicle_id');
        });

        Schema::dropIfExists('vehicles');
    }
};
