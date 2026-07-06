<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'exists:patients,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'gt:0'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id' => __('pos.patient'),
            'items' => __('pos.items'),
            'items.*.qty' => __('pos.qty'),
            'items.*.service_id' => __('pos.item'),
            'items.*.product_id' => __('pos.item'),
        ];
    }
}
