<?php

namespace App\Http\Requests;

use App\Enums\CategorizableType;
use App\Enums\ServiceStatus;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // FormRequest ini dipakai store sekaligus update. Memeriksa 'create'
        // untuk keduanya berarti peran yang hanya boleh membuat ikut lolos
        // saat mengubah, jadi kategorinya ditentukan dari ada-tidaknya
        // kategori yang sedang diubah pada route.
        $category = $this->route('category');

        return $category !== null
            ? ($this->user()?->can('update', $category) ?? false)
            : ($this->user()?->can('create', Category::class) ?? false);
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // Nama ganda dalam satu jenis membuat pemilihan di formulir
                // jadi tebak-tebakan. Unik-nya per klinik per jenis, sama
                // dengan constraint di basis data.
                Rule::unique('categories', 'name')
                    ->where('tenant_id', app()->bound('tenant') ? app('tenant')->id : null)
                    ->where('type', $this->input('type'))
                    ->ignore($category),
            ],
            'type' => ['required', new Enum(CategorizableType::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', new Enum(ServiceStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('category.name'),
            'type' => __('category.type'),
            'description' => __('category.description'),
            'status' => __('category.status'),
        ];
    }
}
