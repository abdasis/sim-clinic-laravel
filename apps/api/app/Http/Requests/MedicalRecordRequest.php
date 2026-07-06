<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'exists:bookings,id'],
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
