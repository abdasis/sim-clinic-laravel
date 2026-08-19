<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'whatsapp' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'notes' => null,
        ];
    }

    /**
     * Pasien yang sudah dinonaktifkan.
     */
    public function trashed(): static
    {
        return $this->state(fn () => ['deleted_at' => now()]);
    }
}
