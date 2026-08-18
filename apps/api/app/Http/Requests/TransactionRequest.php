<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
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
            'items.*.qty' => __('pos.qty'),
            'items.*.service_id' => __('pos.item'),
            'items.*.product_id' => __('pos.item'),
        ];
    }
}
