<?php

namespace App\Http\Requests\CompanyProfile;

class PromoRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('title'),
            // Rich text Tiptap per bahasa; isinya dokumen, bukan string.
            ...$this->translatableRules('description', required: false),
            ...$this->ctaRules(),
            'image_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
