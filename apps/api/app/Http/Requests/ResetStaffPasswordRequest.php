<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Admin menyetel ulang kata sandi seorang staf.
 *
 * Kata sandi lama tidak diminta — admin memang tidak mengetahuinya, dan
 * justru itu keadaan yang membuat penyetelan ulang dibutuhkan.
 */
class ResetStaffPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Jalur ini melewati pemeriksaan kata sandi lama, jadi ia tidak
            // boleh jadi pintu belakang untuk mengganti kata sandi sendiri
            // tanpa mengetahuinya — mis. lewat sesi yang tertinggal terbuka.
            if ($this->route('staff')?->id === $this->user()?->id) {
                $validator->errors()->add('password', __('auth.reset_self_not_allowed'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'password' => __('auth.new_password'),
        ];
    }
}
