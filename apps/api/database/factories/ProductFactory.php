<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\ServiceStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => ProductType::Retail,
            'unit' => fake()->randomElement(['pcs', 'botol', 'tube']),
            'stock_balance' => 0,
            'min_threshold' => 5,
            'price' => fake()->numberBetween(10_000, 500_000),
            'status' => ServiceStatus::Active,
        ];
    }

    /** Bahan pakai treatment: punya stok, tapi tidak dijual. */
    public function consumable(): static
    {
        return $this->state(fn () => ['type' => ProductType::Consumable, 'price' => 0]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ServiceStatus::Archived]);
    }
}
