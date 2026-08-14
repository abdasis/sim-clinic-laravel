<?php

namespace App\Http\Requests\CompanyProfile;

class SlideRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('title'),
            ...$this->translatableRules('subtitle', required: false),
            ...$this->ctaRules(),
            'image_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
