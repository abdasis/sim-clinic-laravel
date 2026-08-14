<?php

namespace App\Http\Requests;

use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
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
            'min_threshold' => __('product.min_threshold'),
            'price' => __('product.price'),
            'status' => __('product.status'),
        ];
    }
}
