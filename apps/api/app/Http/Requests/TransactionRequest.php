<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Models\Booking;
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
            'patient_id' => ['required', 'exists:patients,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'therapist_id' => ['nullable', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'gt:0'],
            // Satu baris mewakili tepat satu layanan atau satu produk.
            'items.*.service_id' => ['nullable', 'required_without:items.*.product_id', 'prohibits:items.*.product_id', 'exists:services,id'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
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
