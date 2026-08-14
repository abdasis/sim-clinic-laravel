<?php

namespace App\Http\Requests\CompanyProfile;

use App\Enums\CompanySectionKey;
use App\Enums\CompanySectionLayout;
use Illuminate\Validation\Rule;

class ContentSectionRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('title', required: false),
            ...$this->translatableRules('body', required: false),
            ...$this->ctaRules(),
            // Satu slot satu isi; slot yang sama tidak boleh dibuat dua kali.
            'section_key' => [
                'required',
                Rule::enum(CompanySectionKey::class),
                Rule::unique('company_content_sections', 'section_key')
                    ->where('tenant_id', app('tenant')->id)
                    ->ignore($this->route('content')),
            ],
            'layout_type' => ['sometimes', Rule::enum(CompanySectionLayout::class)],
            'image_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
