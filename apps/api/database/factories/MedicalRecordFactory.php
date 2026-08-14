<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    protected $model = MedicalRecord::class;

    public function definition(): array
    {
        return [
            // Rekam medis selalu lahir dari kunjungan yang sudah selesai.
            'patient_id' => Patient::factory(),
            'booking_id' => Booking::factory()->done(),
            'author_id' => User::factory(),
            'subjective' => fake()->sentence(),
            'objective' => fake()->sentence(),
            'assessment' => fake()->sentence(),
            'plan' => fake()->sentence(),
        ];
    }

    /**
     * Rekam medis yang sudah dihapus (soft delete).
     */
    public function trashed(): static
    {
        return $this->state(fn () => ['deleted_at' => now()]);
    }
}
