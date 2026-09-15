<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Enums\MembershipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class MembershipTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tier = $this->route('membership_tier');

        return [
            'name' => [
                'required', 'string', 'max:255',
                // Nama tingkat dibaca kasir dan tercetak di nota; kembar cuma
                // bikin ragu mana yang dimaksud.
                Rule::unique('membership_tiers', 'name')
                    ->where('tenant_id', app('tenant')->id)
                    ->ignore($tier?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', new Enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'stacks_with_promo' => ['nullable', 'boolean'],
            'status' => ['nullable', new Enum(MembershipStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('discount_type') !== DiscountType::Percent->value) {
                return;
            }

            // Potongan persen di atas 100 membuat tagihan jadi negatif sebelum
            // dibulatkan ke nol — lebih baik ditolak sejak formulir.
            if ((float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', __('promo.percent_max'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => __('membership.tier_name'),
            'description' => __('membership.description'),
            'discount_type' => __('promo.discount_type'),
            'discount_value' => __('membership.discount_value'),
            'stacks_with_promo' => __('membership.stacks_with_promo'),
            'status' => __('promo.status'),
        ];
    }
}
