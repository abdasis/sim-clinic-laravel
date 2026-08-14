<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'booking_id' => null,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'subtotal' => 100000,
            'paid_amount' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'issued_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => $attributes['subtotal'] ?? 100000,
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['cancelled_at' => now()]);
    }
}
