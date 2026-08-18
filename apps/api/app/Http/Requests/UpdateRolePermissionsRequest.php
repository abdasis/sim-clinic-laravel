<?php

namespace App\Http\Requests;

use App\Actions\Tenant\SyncTenantClinicRolesAction;
use App\Enums\ClinicRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Role::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Peran klinik jumlahnya tetap; tidak ada jalur membuat yang baru.
            'role' => ['required', Rule::in(ClinicRole::values())],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.view' => ['required', 'boolean'],
            'modules.*.manage' => ['required', 'boolean'],
        ];
    }

    /**
     * Nama peran ada di URL, bukan body. Disalin ke input supaya ikut
     * tervalidasi seperti field lain, bukan dipercaya begitu saja.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['role' => $this->route('role')]);
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $known = SyncTenantClinicRolesAction::modules();

                foreach ((array) $this->input('modules', []) as $module => $access) {
                    // Nama modul asing akan melahirkan permission yang tidak
                    // pernah diperiksa siapa pun — kelihatan tersimpan di
                    // layar, tapi tidak membuka apa-apa.
                    if (! in_array($module, $known, true)) {
                        $validator->errors()->add(
                            "modules.{$module}",
                            __('role.unknown_module', ['module' => $module]),
                        );

                        continue;
                    }

                    // Mengelola tanpa bisa melihat adalah keadaan yang tidak
                    // pernah masuk akal: layarnya sendiri tidak akan terbuka.
                    if (($access['manage'] ?? false) && ! ($access['view'] ?? false)) {
                        $validator->errors()->add(
                            "modules.{$module}.manage",
                            __('role.manage_requires_view'),
                        );
                    }
                }
            },
        ];
    }

    public function roleName(): string
    {
        return (string) $this->validated('role');
    }

    /**
     * @return array<string, array{view: bool, manage: bool}>
     */
    public function modules(): array
    {
        return (array) $this->validated('modules');
    }
}
