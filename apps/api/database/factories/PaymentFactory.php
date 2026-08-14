<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'method' => PaymentMethod::cases()[0],
            'amount' => 50000,
            'paid_at' => now(),
        ];
    }
}
