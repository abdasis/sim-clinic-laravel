<?php

namespace Database\Factories;

use App\Models\CompanyProfileSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfileSetting>
 */
class CompanyProfileSettingFactory extends Factory
{
    protected $model = CompanyProfileSetting::class;

    public function definition(): array
    {
        $brand = fake()->company();

        return [
            'is_published' => true,
            'brand_name' => ['id' => $brand, 'en' => $brand],
            'tagline' => ['id' => 'Rawat kulitmu dengan tenang', 'en' => 'Care for your skin, calmly'],
            'logo_path' => null,
            'phone' => fake()->numerify('021#######'),
            'whatsapp' => fake()->numerify('08##########'),
            'email' => fake()->companyEmail(),
            'address' => ['id' => fake()->address(), 'en' => fake()->address()],
            'map_embed_url' => null,
            'social_links' => ['instagram' => 'https://instagram.com/'.fake()->userName()],
            'meta_title' => ['id' => $brand, 'en' => $brand],
            'meta_description' => ['id' => fake()->sentence(), 'en' => fake()->sentence()],
            'chat_widget_enabled' => false,
            'chat_widget_number' => null,
        ];
    }

    /** Landing masih draf — pengunjung belum boleh melihat isinya. */
    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
