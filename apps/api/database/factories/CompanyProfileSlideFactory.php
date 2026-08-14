<?php

namespace Database\Factories;

use App\Enums\CompanyCtaType;
use App\Models\CompanyProfileSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfileSlide>
 */
class CompanyProfileSlideFactory extends Factory
{
    protected $model = CompanyProfileSlide::class;

    public function definition(): array
    {
        return [
            'title' => ['id' => fake()->sentence(3), 'en' => fake()->sentence(3)],
            'subtitle' => ['id' => fake()->sentence(), 'en' => fake()->sentence()],
            'image_path' => 'company-profile/slides/'.fake()->uuid().'.jpg',
            'cta_label' => ['id' => 'Booking Sekarang', 'en' => 'Book Now'],
            'cta_type' => CompanyCtaType::RouteInternal,
            'cta_value' => '/booking',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
