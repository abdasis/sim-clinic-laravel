<?php

namespace App\Http\Requests\CompanyProfile;

use App\Enums\CompanyTreatmentBadge;
use App\Rules\TenantRule;
use Illuminate\Validation\Rule;

class TreatmentRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('title'),
            ...$this->translatableRules('description', required: false),
            // Slug ada di URL publik, jadi harus unik dalam satu tenant saja.
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('company_treatments', 'slug')
                    ->where('tenant_id', app('tenant')->id)
                    ->ignore($this->route('content')),
            ],
            'service_id' => ['nullable', 'integer', TenantRule::exists('services')],
            'image_path' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', Rule::enum(CompanyTreatmentBadge::class)],
            'category_tags' => ['nullable', 'array'],
            'category_tags.*' => ['string', 'max:50'],
            'detail_url' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'slug.unique' => __('company_profile.slug_taken'),
        ];
    }
}
