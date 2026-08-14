<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'patient_id' => Patient::factory(),
            'service_id' => Service::factory(),
            'assignee_id' => User::factory(),
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+1 hour'),
            'status' => BookingStatus::Pending,
            'notes' => null,
        ];
    }

    /**
     * Kunjungan yang sudah selesai — prasyarat rekam medis.
     */
    public function done(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Done,
            'status_changed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'status_changed_at' => now(),
        ]);
    }
}
