<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardSummaryRequest extends FormRequest
{
    /** Dasbor dibaca semua peran klinik; pembatasnya route group, bukan modul. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Rentang panjang membuat grafik harian terlalu rapat untuk dibaca.
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ];
    }

    public function days(): int
    {
        return (int) ($this->validated('days') ?? 14);
    }

    public function attributes(): array
    {
        return ['days' => __('dashboard.range')];
    }
}
