<?php

namespace App\Http\Requests;

use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            // Satu-satunya nomor pasien, dan jalur pengingat serta broadcast
            // berjalan di atasnya — karena itu wajib, sama seperti dulu kolom
            // telepon wajib.
            'whatsapp' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'referred_by' => ['nullable', TenantRule::exists('users')],
            'whatsapp_opt_in' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('patient.name'),
            'birth_date' => __('patient.birth_date'),
            'gender' => __('patient.gender'),
            'whatsapp' => __('patient.whatsapp'),
            'address' => __('patient.address'),
            'notes' => __('patient.notes'),
        ];
    }
}
