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
        Schema::create('fuel_fills', function (Blueprint $table) {
            $table->id();
            $table->date('filled_on');
            $table->double('odometer_km');
            $table->double('liters');
            $table->double('total_cost')->default(0);
            $table->boolean('is_full_tank')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_fills');
    }
};
