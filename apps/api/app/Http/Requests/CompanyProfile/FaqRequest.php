<?php

namespace App\Http\Requests\CompanyProfile;

use App\Rules\TenantRule;

class FaqRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('question'),
            ...$this->translatableRules('answer'),
            // Kosong berarti pertanyaan umum klinik, bukan milik satu treatment.
            'company_treatment_id' => ['nullable', TenantRule::exists('company_treatments')],
        ];
    }
}
