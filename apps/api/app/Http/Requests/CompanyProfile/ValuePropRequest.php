<?php

namespace App\Http\Requests\CompanyProfile;

class ValuePropRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('title'),
            ...$this->translatableRules('description', required: false),
            'icon' => ['nullable', 'string', 'max:100'],
        ];
    }
}
