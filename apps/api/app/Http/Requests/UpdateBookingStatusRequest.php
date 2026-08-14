<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_column(BookingStatus::cases(), 'value'))],
        ];
    }

    public function attributes(): array
    {
        return ['status' => __('booking.status')];
    }
}
