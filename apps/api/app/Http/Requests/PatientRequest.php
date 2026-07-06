<?php

namespace App\Http\Requests;

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
            'phone' => ['required', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('patient.name'),
            'phone' => __('patient.phone'),
            'birth_date' => __('patient.birth_date'),
            'gender' => __('patient.gender'),
            'whatsapp' => __('patient.whatsapp'),
            'address' => __('patient.address'),
            'notes' => __('patient.notes'),
        ];
    }
}
