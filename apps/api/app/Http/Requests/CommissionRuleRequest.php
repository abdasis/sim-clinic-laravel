<?php

namespace App\Http\Requests;

use App\Enums\CommissionRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class CommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(CommissionRuleType::class)],
            'therapist_id' => ['nullable', 'exists:users,id'],
            'amount' => ['nullable', 'numeric', 'gte:0'],
            'percent' => ['nullable', 'numeric', 'gte:0', 'max:100'],
            'min_revenue' => ['nullable', 'numeric', 'gte:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');

            // Aturan yang nilainya nol tidak pernah menghasilkan fee; lebih
            // baik ditolak daripada diam-diam tidak berpengaruh.
            if ($type === CommissionRuleType::RevenuePercent->value) {
                if ((float) $this->input('percent') <= 0) {
                    $validator->errors()->add('percent', __('commission.percent_required'));
                }

                return;
            }

            if ((float) $this->input('amount') <= 0) {
                $validator->errors()->add('amount', __('commission.amount_required'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => __('commission.name'),
            'therapist_id' => __('commission.therapist'),
            'type' => __('commission.type_label'),
            'amount' => __('commission.amount'),
            'percent' => __('commission.percent'),
            'min_revenue' => __('commission.min_revenue'),
        ];
    }
}
