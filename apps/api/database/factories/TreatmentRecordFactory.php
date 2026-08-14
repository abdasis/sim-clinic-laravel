<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\TreatmentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentRecord>
 */
class TreatmentRecordFactory extends Factory
{
    protected $model = TreatmentRecord::class;

    public function definition(): array
    {
        return [
            'medical_record_id' => MedicalRecord::factory(),
            'service_id' => null,
            // Snapshot nama layanan; sengaja tidak ikut berubah saat master diedit.
            'service_name' => fake()->randomElement(['Facial', 'Peeling', 'Laser', 'Botox']),
            'notes' => fake()->sentence(),
        ];
    }
}
