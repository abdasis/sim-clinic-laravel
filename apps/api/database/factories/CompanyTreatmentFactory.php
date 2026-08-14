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
        $title = fake()->randomElement(['Facial Glow', 'Chemical Peeling', 'Laser Rejuvenation']);

        return [
            'service_id' => null,
            'slug' => fake()->unique()->slug(2),
            'title' => ['id' => $title, 'en' => $title],
            'description' => ['id' => fake()->sentence(), 'en' => fake()->sentence()],
            'image_path' => 'company-profile/treatments/'.fake()->uuid().'.jpg',
            'badge' => null,
            'category_tags' => ['rejuvenation'],
            'detail_url' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['badge' => CompanyTreatmentBadge::Featured]);
    }
}
