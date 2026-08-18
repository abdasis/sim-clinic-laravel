<?php

namespace App\Http\Requests;

use App\Enums\CategorizableType;
use App\Enums\ServiceStatus;
use App\Rules\TenantRule;
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
            // Kategori entitas, bukan enum lagi. `type` ikut diperiksa supaya
            // kategori produk tidak bisa menempel ke layanan.
            'category_id' => [
                'nullable',
                TenantRule::exists('categories')->where('type', CategorizableType::Service->value),
            ],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'gte:0'],
            'duration_minutes' => ['required', 'integer', 'gte:1', 'max:600'],
            'status' => ['nullable', new Enum(ServiceStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('service.name'),
            'category_id' => __('service.category'),
            'description' => __('service.description'),
            'price' => __('service.price'),
            'duration_minutes' => __('service.duration_minutes'),
            'status' => __('service.status'),
        ];
    }
}
