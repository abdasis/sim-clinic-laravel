<?php

namespace App\Services;

use App\Actions\Patient\CreatePatientAction;
use App\Actions\Patient\DeactivatePatientAction;
use App\Actions\Patient\DeletePatientAction;
use App\Actions\Patient\UpdatePatientAction;
use App\Models\Patient;
use App\Support\PatientReferences;

/**
 * Use case data pasien. Nomor telepon ganda hanya diperingatkan, tidak
 * diblokir — satu keluarga kerap memakai satu nomor.
 */
class PatientService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0:Patient, 1:?Patient}
     */
    public function create(array $attributes): array
    {
        $patient = app(CreatePatientAction::class)->handle($attributes);

        return [$patient, $this->findDuplicateByPhone($patient)];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0:Patient, 1:?Patient}
     */
    public function update(Patient $patient, array $attributes): array
    {
        $patient = app(UpdatePatientAction::class)->handle($patient, $attributes);

        return [$patient, $this->findDuplicateByPhone($patient)];
    }

    public function deactivate(Patient $patient): Patient
    {
        return app(DeactivatePatientAction::class)->handle($patient);
    }

    /**
     * Hapus pasien: permanen bila belum meninggalkan jejak, diarsipkan bila
     * sudah pernah datang. Rekam medis dan nota tidak boleh ikut hilang hanya
     * karena datanya dirapikan.
     *
     * @return bool true bila benar-benar dihapus, false bila diarsipkan
     */
    public function delete(Patient $patient): bool
    {
        if (app(PatientReferences::class)->has($patient->id)) {
            $this->deactivate($patient);

            return false;
        }

        app(DeletePatientAction::class)->handle($patient);

        return true;
    }

    /**
     * Pasien lain di tenant yang sama dengan nomor telepon identik.
     * Scope tenant sudah otomatis dari TenantScope.
     */
    private function findDuplicateByPhone(Patient $patient): ?Patient
    {
        if (blank($patient->phone)) {
            return null;
        }

        return Patient::query()
            ->where('phone', $patient->phone)
            ->whereKeyNot($patient->getKey())
            ->first();
    }
}
