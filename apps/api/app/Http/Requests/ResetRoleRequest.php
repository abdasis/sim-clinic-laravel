<?php

namespace App\Http\Requests;

use App\Enums\ClinicRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ResetRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reset', Role::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(ClinicRole::values())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['role' => $this->route('role')]);
    }

    public function roleName(): string
    {
        return (string) $this->validated('role');
    }
}
