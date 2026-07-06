<?php

namespace App\Http\Requests;

use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'gte:0'],
            'status' => ['nullable', new Enum(ServiceStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('service.name'),
            'description' => __('service.description'),
            'price' => __('service.price'),
            'status' => __('service.status'),
        ];
    }
}
