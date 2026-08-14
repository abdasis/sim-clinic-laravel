<?php

namespace Database\Factories;

use App\Models\CompanyTestimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyTestimonial>
 */
class CompanyTestimonialFactory extends Factory
{
    protected $model = CompanyTestimonial::class;

    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'author_role' => ['id' => 'Pasien', 'en' => 'Patient'],
            'quote' => [
                'id' => CompanyValuePropFactory::richText(fake()->sentence()),
                'en' => CompanyValuePropFactory::richText(fake()->sentence()),
            ],
            'avatar_path' => null,
            'rating' => fake()->numberBetween(4, 5),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
