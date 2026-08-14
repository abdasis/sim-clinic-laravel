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
            'cta_type' => CompanyCtaType::Whatsapp,
            'cta_value' => fake()->numerify('08##########'),
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /** Promo yang masanya sudah lewat. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
