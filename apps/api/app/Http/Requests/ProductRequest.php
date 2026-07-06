<?php

namespace App\Http\Requests;

use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'stock_balance' => ['required', 'integer', 'gte:0'],
            'min_threshold' => ['required', 'integer', 'gte:0'],
            'price' => ['required', 'numeric', 'gte:0'],
            'status' => ['nullable', new Enum(ServiceStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('product.name'),
            'unit' => __('product.unit'),
            'stock_balance' => __('product.stock_balance'),
            'min_threshold' => __('product.min_threshold'),
            'price' => __('product.price'),
            'status' => __('product.status'),
        ];
    }
}
