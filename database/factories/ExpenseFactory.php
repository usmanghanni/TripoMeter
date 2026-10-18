<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spent_on' => today(),
            'category' => fake()->randomElement(array_keys(Expense::CATEGORIES)),
            'amount' => fake()->randomFloat(2, 500, 20000),
            'odometer_km' => fake()->numberBetween(1000, 50000),
        ];
    }

    public function oilChange(): static
    {
        return $this->state(['category' => 'oil_change']);
    }
}
