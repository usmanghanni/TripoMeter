<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Corolla', 'Civic', 'City', 'Swift', 'CD 70']),
            'oil_change_interval_km' => 5000,
            'oil_change_interval_months' => 6,
        ];
    }
}
