<?php

namespace App\Http\Requests;

use App\Enums\StatsModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatsModuleRequest extends FormRequest
{
    /** Izin per modul diperiksa di controller, setelah modulnya lolos validasi. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Modul datang dari segmen route, bukan query string; disalin ke input
     * supaya ikut divalidasi seperti field biasa.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['module' => $this->route('module')]);
    }

    public function rules(): array
    {
        return [
            'module' => ['required', Rule::in(StatsModule::values())],
            // Rentang panjang membuat grafik harian terlalu rapat untuk dibaca.
            'days' => ['sometimes', 'integer', 'min:7', 'max:90'],
        ];
    }

    public function module(): StatsModule
    {
        return StatsModule::from($this->validated('module'));
    }

    public function days(): int
    {
        return (int) ($this->validated('days') ?? 30);
    }

    public function attributes(): array
    {
        return [
            'module' => __('stats.module'),
            'days' => __('stats.range'),
        ];
    }
}
