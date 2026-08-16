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
            'therapist_id' => ['nullable', TenantRule::exists('users')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'gt:0'],
            // Satu baris mewakili tepat satu layanan atau satu produk.
            'items.*.service_id' => ['nullable', 'required_without:items.*.product_id', 'prohibits:items.*.product_id', TenantRule::exists('services')],
            'items.*.product_id' => ['nullable', TenantRule::exists('products')],
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
            'therapist_id' => __('commission.therapist'),
            'items' => __('pos.items'),
            'items.*.qty' => __('pos.qty'),
            'items.*.service_id' => __('pos.item'),
            'items.*.product_id' => __('pos.item'),
        ];
    }
}
