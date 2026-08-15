<?php

namespace App\Http\Requests;

use App\Enums\AppearanceOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Preferensi tampilan hanya boleh diubah pemiliknya sendiri, jadi tidak ada
 * permission atau policy yang terlibat — cukup pastikan ada yang login.
 */
class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'accent' => ['required', Rule::in(AppearanceOption::ACCENTS)],
            'mode' => ['required', Rule::in(AppearanceOption::MODES)],
            'sidebar_variant' => ['required', Rule::in(AppearanceOption::SIDEBAR_VARIANTS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'accent' => __('preferences.accent'),
            'mode' => __('preferences.mode'),
            'sidebar_variant' => __('preferences.sidebar_variant'),
        ];
    }

    /** @return array<string, string> */
    public function appearance(): array
    {
        return $this->safe()->only(['accent', 'mode', 'sidebar_variant']);
    }
}
