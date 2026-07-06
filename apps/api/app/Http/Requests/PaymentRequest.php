<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', new Enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'method' => __('pos.method'),
            'amount' => __('pos.amount'),
            'paid_at' => __('pos.payment'),
        ];
    }
}
