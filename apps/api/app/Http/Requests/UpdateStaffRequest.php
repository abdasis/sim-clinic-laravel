<?php

namespace App\Http\Requests;

use App\Enums\ClinicRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Email milik staf yang sedang diubah tentu saja tidak bentrok
            // dengan dirinya sendiri.
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('staff')?->id),
            ],
            'clinic_role' => ['required', new Enum(ClinicRole::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('staff.name'),
            'email' => __('staff.email'),
            'clinic_role' => __('staff.clinic_role'),
        ];
    }
}
