<?php

namespace App\Http\Requests;

use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class TreatmentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', TenantRule::exists('services')],
            'service_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'service_id' => __('medical_record.treatments'),
            'service_name' => __('medical_record.treatments'),
            'notes' => __('medical_record.plan'),
        ];
    }
}
