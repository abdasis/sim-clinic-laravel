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
            'logo_path' => null,
            'site_name' => ['id' => $brand, 'en' => $brand],
            'copyright_text' => $brand,
            'chat_channels' => [[
                'type' => 'whatsapp',
                'url' => 'https://wa.me/62'.fake()->numerify('8##########'),
                'label' => ['id' => 'Chat WhatsApp', 'en' => 'Chat on WhatsApp'],
            ]],
            'social_links' => [[
                'platform' => 'instagram',
                'url' => 'https://instagram.com/'.fake()->userName(),
                'icon' => 'instagram',
            ]],
            'marketplace_links' => [],
            'default_locale' => 'id',
            'is_published' => true,
        ];
    }

    /** Landing masih draf — pengunjung belum boleh melihat isinya. */
    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
