<?php

namespace App\Http\Requests;

use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class MedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('medical_record.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Kunjungan penentu milik siapa catatan ini, jadi tidak bisa
            // dipindah setelah rekam medisnya dibuat.
            ...$this->isMethod('POST')
                ? ['booking_id' => ['required', TenantRule::exists('bookings')]]
                : ['booking_id' => ['prohibited']],
            'anamnesis' => ['nullable', 'string'],
            'skincare_history' => ['nullable', 'string'],
            'allergy_history' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'booking_id' => __('medical_record.booking'),
            'anamnesis' => __('medical_record.anamnesis'),
            'skincare_history' => __('medical_record.skincare_history'),
            'allergy_history' => __('medical_record.allergy_history'),
        ];
    }
}
