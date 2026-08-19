<?php

namespace App\Http\Requests\CompanyProfile;

use App\Support\LocaleText;
use Illuminate\Validation\Rule;

class SettingsRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            ...$this->translatableRules('site_name', required: false),
            'logo_path' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'receipt_note' => ['nullable', 'string', 'max:160'],
            'copyright_text' => ['nullable', 'string', 'max:255'],
            'default_locale' => ['sometimes', Rule::in(LocaleText::SUPPORTED)],
            'is_published' => ['sometimes', 'boolean'],

            // Satu rentang per hari; hari tutup cukup is_open false.
            'operating_hours' => ['nullable', 'array'],
            'operating_hours.*.is_open' => ['boolean'],
            'operating_hours.*.open' => ['nullable', 'string', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'string', 'date_format:H:i', 'after:operating_hours.*.open'],

            'chat_channels' => ['nullable', 'array'],
            'chat_channels.*.type' => ['required', 'string', 'max:50'],
            'chat_channels.*.url' => ['required', 'string', 'max:255'],
            'chat_channels.*.label' => ['nullable', 'array'],

            'social_links' => ['nullable', 'array'],
            'social_links.*.platform' => ['required', 'string', 'max:50'],
            'social_links.*.url' => ['required', 'url', 'max:255'],
            'social_links.*.icon' => ['nullable', 'string', 'max:50'],

            'marketplace_links' => ['nullable', 'array'],
            'marketplace_links.*.name' => ['required', 'string', 'max:50'],
            'marketplace_links.*.url' => ['required', 'url', 'max:255'],
            'marketplace_links.*.icon' => ['nullable', 'string', 'max:50'],
        ];
    }
}
