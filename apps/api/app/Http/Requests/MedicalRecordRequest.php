<?php

namespace App\Http\Requests;

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
                ? ['booking_id' => ['required', 'exists:bookings,id']]
                : ['booking_id' => ['prohibited']],
            'subjective' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'plan' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'booking_id' => __('medical_record.booking'),
            'subjective' => __('medical_record.subjective'),
            'objective' => __('medical_record.objective'),
            'assessment' => __('medical_record.assessment'),
            'plan' => __('medical_record.plan'),
        ];
    }
}
