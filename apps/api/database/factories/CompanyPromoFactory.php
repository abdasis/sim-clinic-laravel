<?php

namespace Database\Factories;

use App\Enums\CompanyCtaType;
use App\Models\CompanyPromo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyPromo>
 */
class CompanyPromoFactory extends Factory
{
    protected $model = CompanyPromo::class;

    public function definition(): array
    {
        return [
            'title' => ['id' => 'Promo Akhir Bulan', 'en' => 'Month End Promo'],
            'description' => [
                'id' => CompanyValuePropFactory::richText(fake()->sentence()),
                'en' => CompanyValuePropFactory::richText(fake()->sentence()),
            ],
            'image_path' => 'company-profile/promos/'.fake()->uuid().'.jpg',
            'cta_label' => ['id' => 'Ambil Promo', 'en' => 'Claim Promo'],
            'cta_url' => 'https://wa.me/6281234567890',
            'cta_type' => CompanyCtaType::Whatsapp,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
