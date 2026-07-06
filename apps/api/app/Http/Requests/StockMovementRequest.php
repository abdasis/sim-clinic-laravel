<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['in', 'out_manual'])],
            'quantity' => ['required', 'integer', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => __('inventory.type'),
            'quantity' => __('inventory.quantity'),
            'note' => __('inventory.note'),
        ];
    }
}
