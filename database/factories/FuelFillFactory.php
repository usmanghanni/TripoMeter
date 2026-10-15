<?php

namespace Database\Factories;

use App\Models\FuelFill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelFill>
 */
class FuelFillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filled_on' => today(),
            'odometer_km' => fake()->numberBetween(1000, 50000),
            'liters' => fake()->randomFloat(2, 10, 40),
            'total_cost' => fake()->randomFloat(2, 500, 4000),
            'is_full_tank' => true,
        ];
    }
}
