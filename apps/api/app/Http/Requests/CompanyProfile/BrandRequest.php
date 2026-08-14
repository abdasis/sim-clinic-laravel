<?php

namespace App\Http\Requests\CompanyProfile;

class BrandRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('name'),
            ...$this->translatableRules('description', required: false),
            'logo_path' => ['nullable', 'string', 'max:255'],
            'external_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
