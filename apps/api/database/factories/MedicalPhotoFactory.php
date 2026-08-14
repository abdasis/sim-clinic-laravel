<?php

namespace Database\Factories;

use App\Enums\MedicalPhotoType;
use App\Models\MedicalPhoto;
use App\Models\MedicalRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalPhoto>
 */
class MedicalPhotoFactory extends Factory
{
    protected $model = MedicalPhoto::class;

    public function definition(): array
    {
        return [
            'medical_record_id' => MedicalRecord::factory(),
            'type' => fake()->randomElement(MedicalPhotoType::cases()),
            'path' => 'medical-photos/'.fake()->uuid().'.jpg',
        ];
    }

    public function before(): static
    {
        return $this->state(fn () => ['type' => MedicalPhotoType::Before]);
    }

    public function after(): static
    {
        return $this->state(fn () => ['type' => MedicalPhotoType::After]);
    }
}
