<?php

namespace Database\Factories;

use App\Models\CompanyBrand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyBrand>
 */
class CompanyBrandFactory extends Factory
{
    protected $model = CompanyBrand::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => ['id' => $name, 'en' => $name],
            'description' => ['id' => fake()->sentence(), 'en' => fake()->sentence()],
            'logo_path' => 'company-profile/brands/'.fake()->uuid().'.png',
            'external_url' => fake()->url(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
