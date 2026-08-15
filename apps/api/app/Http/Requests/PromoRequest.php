<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Enums\PromoStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class PromoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', new Enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', new Enum(PromoStatus::class)],

            // Promo tanpa sasaran tidak pernah berlaku pada apa pun, jadi
            // minimal satu layanan atau produk wajib dipilih.
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.type' => ['required', Rule::in(['service', 'product'])],
            'targets.*.id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('discount_type') !== DiscountType::Percent->value) {
                return;
            }

            // Potongan persen di atas 100 membuat harga jadi negatif sebelum
            // dibulatkan ke nol — lebih baik ditolak sejak formulir.
            if ((float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', __('promo.percent_max'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => __('promo.name'),
            'description' => __('promo.description'),
            'discount_type' => __('promo.discount_type'),
            'discount_value' => __('promo.discount_value'),
            'starts_at' => __('promo.starts_at'),
            'ends_at' => __('promo.ends_at'),
            'status' => __('promo.status'),
            'targets' => __('promo.targets'),
        ];
    }
}
