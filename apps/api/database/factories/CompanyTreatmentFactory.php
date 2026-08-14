<?php

namespace Database\Factories;

use App\Enums\CompanyTreatmentBadge;
use App\Models\CompanyTreatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyTreatment>
 */
class CompanyTreatmentFactory extends Factory
{
    protected $model = CompanyTreatment::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Facial Glow', 'Chemical Peeling', 'Laser Rejuvenation']);

        return [
            'service_id' => null,
            'slug' => fake()->unique()->slug(2),
            'name' => ['id' => $name, 'en' => $name],
            'excerpt' => ['id' => fake()->sentence(), 'en' => fake()->sentence()],
            'description' => [
                'id' => CompanyValuePropFactory::richText(fake()->paragraph()),
                'en' => CompanyValuePropFactory::richText(fake()->paragraph()),
            ],
            'image_path' => 'company-profile/treatments/'.fake()->uuid().'.jpg',
            'badge' => null,
            'price_label' => 'Mulai '.fake()->numberBetween(150, 900).'rb',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['badge' => CompanyTreatmentBadge::Featured]);
    }
}
