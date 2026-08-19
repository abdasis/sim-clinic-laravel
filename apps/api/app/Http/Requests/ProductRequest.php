<?php

namespace App\Http\Requests;

use App\Enums\CategorizableType;
use App\Enums\ProductType;
use App\Enums\ServiceStatus;
use App\Rules\TenantRule;
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
            'type' => ['nullable', new Enum(ProductType::class)],
            'category_id' => [
                'nullable',
                TenantRule::exists('categories')->where('type', CategorizableType::Product->value),
            ],
            // Satuan kini data master. Nullable karena produk lama boleh
            // tidak punya pemetaan, dan hanya satuan aktif yang boleh dipilih
            // supaya yang sudah diarsipkan tidak kembali lewat formulir.
            'unit_id' => [
                'nullable',
                TenantRule::exists('units')->where('status', ServiceStatus::Active->value),
            ],
            'min_threshold' => ['required', 'integer', 'gte:0'],
            // Bahan pakai tidak dijual; harganya sudah dipaksa nol di
            // prepareForValidation, jadi form tidak perlu mengirimnya.
            'price' => ['required', 'numeric', 'gte:0'],
            'status' => ['nullable', new Enum(ServiceStatus::class)],
            // Dokumen Tiptap; bentuk node-nya tidak divalidasi karena isinya
            // datang dari editor admin, bukan dari luar.
            'knowledge' => ['nullable', 'array'],
        ];
    }

    private function isRetail(): bool
    {
        return ($this->input('type') ?? ProductType::Retail->value) === ProductType::Retail->value;
    }

    /**
     * Bahan pakai selalu berharga jual nol: kolomnya wajib di database dan
     * nilai selain nol akan menyesatkan laporan penjualan. Diisi sebelum
     * validasi supaya ikut terbawa ke validated().
     */
    protected function prepareForValidation(): void
    {
        if (! $this->isRetail()) {
            $this->merge(['price' => 0]);
        }
    }

    public function attributes(): array
    {
        return [
            'name' => __('product.name'),
            'type' => __('product.type'),
            'category_id' => __('product.category'),
            'unit_id' => __('product.unit'),
            'min_threshold' => __('product.min_threshold'),
            'price' => __('product.price'),
            'status' => __('product.status'),
        ];
    }
}
