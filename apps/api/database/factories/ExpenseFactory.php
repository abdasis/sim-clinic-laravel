<?php

namespace Database\Factories;

use App\Enums\CategorizableType;
use App\Models\Category;
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
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(50, 5000) * 1000,
            'note' => null,
        ];
    }

    /**
     * Kategori bukan kolom lagi melainkan baris pivot, jadi dipasang setelah
     * barisnya ada — nama yang sama dipakai ulang, tidak dibuat berkali-kali.
     */
    public function category(string $name): static
    {
        return $this->afterCreating(function (Expense $expense) use ($name): void {
            $category = Category::query()->firstOrCreate(
                ['name' => $name, 'type' => CategorizableType::Expense],
                ['status' => 'active'],
            );

            $expense->syncCategory($category->id);
        });
    }
}
