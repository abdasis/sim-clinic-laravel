<?php

namespace App\Http\Requests\CompanyProfile;

use App\Enums\CompanyCtaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dasar bersama form request konten company profile: otorisasi modul,
 * aturan urutan/aktif, dan validasi peta bahasa.
 */
abstract class CompanyContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('content.manage') ?? false;
    }

    /**
     * Aturan yang dipakai semua entitas berurutan.
     *
     * @return array<string, mixed>
     */
    protected function commonRules(): array
    {
        return [
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Peta bahasa. Versi Indonesia wajib karena jadi fallback saat versi
     * lain belum ditulis — tanpa itu halaman bisa tampil kosong.
     *
     * @return array<string, mixed>
     */
    protected function translatableRules(string $field, bool $required = true): array
    {
        return [
            $field => [$required ? 'required' : 'nullable', 'array'],
            $field.'.id' => [$required ? 'required' : 'nullable'],
            $field.'.en' => ['nullable'],
        ];
    }

    /**
     * Tombol ajakan: jenisnya menentukan cara `cta_url` dibaca, dan tautan
     * wajib ada begitu jenisnya dipilih.
     *
     * @return array<string, mixed>
     */
    protected function ctaRules(): array
    {
        return [
            ...$this->translatableRules('cta_label', required: false),
            'cta_type' => ['nullable', Rule::enum(CompanyCtaType::class)],
            'cta_url' => ['nullable', 'string', 'max:255', 'required_with:cta_type'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.id.required' => __('company_profile.locale_id_required'),
        ];
    }
}
