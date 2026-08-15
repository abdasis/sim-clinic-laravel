<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\PromoStatus;
use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
{
    protected $model = Promo::class;

    public function definition(): array
    {
        return [
            'name' => 'Promo '.fake()->unique()->word(),
            'description' => fake()->sentence(),
            'discount_type' => DiscountType::Percent,
            'discount_value' => fake()->numberBetween(5, 40),
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDays(7)->toDateString(),
            'status' => PromoStatus::Active,
        ];
    }

    /** Promo yang tanggalnya sudah lewat. */
    public function ended(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(10)->toDateString(),
            'ends_at' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => PromoStatus::Inactive]);
    }
}
