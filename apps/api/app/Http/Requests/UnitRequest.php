<?php

namespace App\Http\Requests;

use App\Enums\ServiceStatus;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Unit::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                // Unik per klinik, sama dengan constraint di basis data —
                // supaya penolakannya berupa pesan formulir, bukan galat SQL.
                Rule::unique('units', 'name')
                    ->where('tenant_id', app()->bound('tenant') ? app('tenant')->id : null)
                    ->ignore($this->route('unit')),
            ],
            'status' => ['nullable', new Enum(ServiceStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('unit.name'),
            'status' => __('unit.status'),
        ];
    }
}
