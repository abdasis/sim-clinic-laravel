<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Mengganti kata sandi sendiri.
 *
 * Kata sandi lama tetap diminta walau penggunanya sudah login: sesi yang
 * tertinggal terbuka di komputer klinik cukup untuk mengunci pemiliknya
 * keluar dari akunnya sendiri kalau syarat ini tidak ada.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            // `confirmed` menuntut kolom kedua yang sama persis: salah ketik
            // pada kata sandi yang tidak terlihat baru ketahuan saat gagal
            // masuk, dan saat itu tidak ada lagi yang tahu ejaan benarnya.
            'password' => ['required', 'string', Password::min(8), 'different:current_password', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => __('auth.current_password'),
            'password' => __('auth.new_password'),
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => __('auth.current_password_wrong'),
            'password.different' => __('auth.password_must_differ'),
        ];
    }
}
