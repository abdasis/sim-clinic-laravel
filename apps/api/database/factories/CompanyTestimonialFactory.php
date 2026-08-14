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
            'quote' => [
                'id' => CompanyValuePropFactory::richText(fake()->sentence()),
                'en' => CompanyValuePropFactory::richText(fake()->sentence()),
            ],
            'author_name' => fake()->name(),
            'since_year' => fake()->numberBetween(2018, 2025),
            'avatar_path' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
