<?php

namespace App\Http\Requests;

use App\Enums\MembershipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
            'status' => ['nullable', new Enum(MembershipStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('membership.tier_name'),
            'description' => __('membership.description'),
            'status' => __('promo.status'),
        ];
    }
}
