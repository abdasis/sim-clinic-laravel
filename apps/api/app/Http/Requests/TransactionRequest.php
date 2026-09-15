<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Enums\DiscountType;
use App\Models\Booking;
use App\Rules\TenantRule;
use App\Support\LoyaltyPoints;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transaction.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', TenantRule::exists('patients')],
            'booking_id' => ['nullable', TenantRule::exists('bookings')],
            // Pelaksana kunjungan; boleh lebih dari satu, boleh kosong untuk
            // penjualan produk yang tidak melibatkan tindakan.
            'performer_ids' => ['nullable', 'array'],
            'performer_ids.*' => [TenantRule::exists('users')],
            // Boleh mundur untuk mencatat penjualan yang terlewat, tidak
            // boleh maju: nota bertanggal besok tidak punya arti.
            'issued_at' => ['nullable', 'date', 'before_or_equal:now'],
            // Potongan di tingkat nota, di luar promo yang menempel per
            // barang. Pecahan diizinkan: 70,5% adalah angka yang wajar.
            'discount_type' => ['nullable', new Enum(DiscountType::class)],
            'discount_value' => ['nullable', 'numeric', 'gt:0', 'required_with:discount_type'],
            // Poin yang ditukar jadi potongan. Nol berarti tidak menukar;
            // minimumnya dijaga di withValidator supaya nol tidak ikut
            // tertolak. Kelebihan di atas tagihan dipangkas server.
            'points_redeemed' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'gt:0'],
            // Satu baris mewakili tepat satu layanan atau satu produk.
            'items.*.service_id' => ['nullable', 'required_without:items.*.product_id', 'prohibits:items.*.product_id', TenantRule::exists('services')],
            'items.*.product_id' => ['nullable', TenantRule::exists('products')],
            // Penawar baris ini. Null berarti pasien membeli atas kemauan
            // sendiri, jadi tidak masuk target penjualan siapa pun.
            'items.*.offered_by' => ['nullable', TenantRule::exists('users')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('discount_type') === DiscountType::Percent->value
                && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', __('pos.discount_percent_max'));
            }

            $points = (int) $this->input('points_redeemed', 0);

            if ($points > 0 && $points < LoyaltyPoints::MIN_REDEEM) {
                $validator->errors()->add(
                    'points_redeemed',
                    __('pos.points_min_redeem', ['min' => LoyaltyPoints::MIN_REDEEM]),
                );
            }

            $bookingId = $this->input('booking_id');

            if ($bookingId === null) {
                return;
            }

            // Transaksi hanya boleh menagih kunjungan yang benar-benar selesai.
            $booking = Booking::find($bookingId);

            if ($booking !== null && $booking->status !== BookingStatus::Done) {
                $validator->errors()->add('booking_id', __('pos.booking_not_done'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'patient_id' => __('pos.patient'),
            'booking_id' => __('booking.title'),
            'issued_at' => __('pos.transaction_date'),
            'performer_ids' => __('pos.performers'),
            'performer_ids.*' => __('pos.performers'),
            'items.*.offered_by' => __('pos.offered_by'),
            'items' => __('pos.items'),
            'discount_type' => __('pos.discount_type'),
            'discount_value' => __('pos.discount_value'),
            'points_redeemed' => __('pos.points_redeem'),
            'items.*.qty' => __('pos.qty'),
            'items.*.service_id' => __('pos.item'),
            'items.*.product_id' => __('pos.item'),
        ];
    }
}
