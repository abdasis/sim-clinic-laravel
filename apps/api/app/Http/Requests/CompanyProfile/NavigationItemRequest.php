<?php

namespace App\Http\Requests\CompanyProfile;

use App\Enums\CompanyLinkType;
use App\Enums\CompanyNavPosition;
use Illuminate\Validation\Rule;

class NavigationItemRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->translatableRules('label'),
            'link_type' => ['required', Rule::enum(CompanyLinkType::class)],
            'position' => ['required', Rule::enum(CompanyNavPosition::class)],
            // Menu tanpa tujuan tidak ada gunanya, apa pun jenis tautannya.
            'url' => ['required', 'string', 'max:255'],
            'is_cta' => ['sometimes', 'boolean'],
        ];
    }
}
