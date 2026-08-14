<?php

namespace Database\Factories;

use App\Models\CompanyValueProp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyValueProp>
 */
class CompanyValuePropFactory extends Factory
{
    protected $model = CompanyValueProp::class;

    public function definition(): array
    {
        $title = fake()->randomElement(['Dokter Berpengalaman', 'Alat Modern', 'Produk Original']);

        return [
            'icon' => fake()->randomElement(['sparkles', 'shield-check', 'heart']),
            'title' => ['id' => $title, 'en' => $title],
            'description' => ['id' => fake()->sentence(), 'en' => fake()->sentence()],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Dokumen Tiptap paling sederhana: satu paragraf. Dipakai factory lain
     * yang field-nya memang rich-text.
     *
     * @return array<string, mixed>
     */
    public static function richText(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ]],
        ];
    }
}
