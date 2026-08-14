<?php

namespace Database\Factories;

use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionItem>
 */
class TransactionItemFactory extends Factory
{
    protected $model = TransactionItem::class;

    public function definition(): array
    {
        return [
            'product_id' => null,
            'service_id' => null,
            'name' => fake()->words(2, true),
            'unit_price' => 50000,
            'qty' => 1,
            'subtotal' => 50000,
        ];
    }
}
