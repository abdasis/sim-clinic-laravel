<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'spent_at' => now()->toDateString(),
            'category' => ExpenseCategory::Operational,
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(50, 5000) * 1000,
            'note' => null,
        ];
    }
}
