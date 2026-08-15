<?php

namespace Database\Factories;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Facial', 'Peeling', 'Laser', 'Botox']).' '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(50, 500) * 1000,
            'duration_minutes' => fake()->numberBetween(15, 120),
            'status' => ServiceStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ServiceStatus::Archived]);
    }
}
