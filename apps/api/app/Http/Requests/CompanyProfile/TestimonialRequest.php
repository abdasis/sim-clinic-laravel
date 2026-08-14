<?php

namespace App\Http\Requests\CompanyProfile;

class TestimonialRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('quote'),
            'author_name' => ['required', 'string', 'max:255'],
            'since_year' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
