<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            // Penanda tampilan saja; rentangnya sudah ditentukan from-to.
            'view' => ['nullable', Rule::in(['day', 'week', 'month'])],
        ];
    }
}
