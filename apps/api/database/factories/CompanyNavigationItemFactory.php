<?php

namespace Database\Factories;

use App\Enums\CompanyLinkType;
use App\Enums\CompanyNavPosition;
use App\Models\CompanyNavigationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyNavigationItem>
 */
class CompanyNavigationItemFactory extends Factory
{
    protected $model = CompanyNavigationItem::class;

    public function definition(): array
    {
        $label = fake()->randomElement(['Treatment', 'Promo', 'Tentang', 'Kontak']);

        return [
            'position' => CompanyNavPosition::Header,
            'label' => ['id' => $label, 'en' => $label],
            'link_type' => CompanyLinkType::AnchorSection,
            'link_value' => '#'.fake()->unique()->slug(1),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function footer(): static
    {
        return $this->state(fn () => ['position' => CompanyNavPosition::Footer]);
    }
}
