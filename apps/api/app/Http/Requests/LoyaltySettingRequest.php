<?php

namespace App\Http\Requests;

use App\Models\LoyaltySetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LoyaltySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', LoyaltySetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Tarif nol membuat pembagian poin meledak dan penukaran jadi
            // gratis tak terbatas, jadi keduanya wajib di atas nol.
            'earn_rate' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'redeem_rate' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'min_redeem' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $earn = (float) $this->input('earn_rate');
            $redeem = (float) $this->input('redeem_rate');

            if ($earn <= 0 || $redeem <= 0) {
                return;
            }

            // Poin yang menebus lebih besar daripada belanja yang
            // menghasilkannya membuat tiap kunjungan mencetak potongan untuk
            // kunjungan berikutnya, tanpa henti. Bukan tarif murah hati —
            // tarif yang tidak bisa dibayar klinik mana pun.
            if ($redeem >= $earn) {
                $validator->errors()->add('redeem_rate', __('loyalty.redeem_exceeds_earn'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'earn_rate' => __('loyalty.earn_rate'),
            'redeem_rate' => __('loyalty.redeem_rate'),
            'min_redeem' => __('loyalty.min_redeem'),
        ];
    }
}
