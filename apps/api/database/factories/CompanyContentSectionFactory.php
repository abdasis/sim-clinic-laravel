<?php

namespace Database\Factories;

use App\Enums\CompanyCtaType;
use App\Enums\CompanySectionKey;
use App\Enums\CompanySectionLayout;
use App\Models\CompanyContentSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyContentSection>
 */
class CompanyContentSectionFactory extends Factory
{
    protected $model = CompanyContentSection::class;

    public function definition(): array
    {
        return [
            'section_key' => CompanySectionKey::PharmaBanner,
            'title' => ['id' => 'Apotek Klinik', 'en' => 'Clinic Pharmacy'],
            'body' => [
                'id' => CompanyValuePropFactory::richText(fake()->sentence()),
                'en' => CompanyValuePropFactory::richText(fake()->sentence()),
            ],
            'image_path' => null,
            'cta_label' => ['id' => 'Lihat Produk', 'en' => 'See Products'],
            'cta_url' => fake()->url(),
            'cta_type' => CompanyCtaType::External,
            'layout_type' => CompanySectionLayout::Banner,
            'is_active' => true,
        ];
    }

    public function key(CompanySectionKey $key): static
    {
        return $this->state(fn () => ['section_key' => $key]);
    }
}
