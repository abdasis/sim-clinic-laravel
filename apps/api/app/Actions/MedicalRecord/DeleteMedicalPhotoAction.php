<?php

namespace App\Actions\MedicalRecord;

use App\Actions\LogAuditAction;
use App\Models\MedicalPhoto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Hapus satu foto klinis: barisnya dulu, berkasnya belakangan.
 *
 * Urutannya sengaja begitu. Kalau berkas dibuang lebih dulu lalu penghapusan
 * barisnya gagal, rekam medis menyisakan foto yang tidak bisa dibuka
 * siapa pun — kerusakan yang tidak bisa dibatalkan. Sebaliknya, berkas yatim
 * di disk hanya memakan ruang dan masih bisa dibereskan belakangan, jadi
 * kegagalan menghapusnya dicatat sebagai peringatan, bukan digagalkan.
 */
class DeleteMedicalPhotoAction
{
    public function handle(MedicalPhoto $photo): MedicalPhoto
    {
        $attributes = $photo->getAttributes();

        try {
            $photo->delete();
        } catch (Throwable $e) {
            Log::error('Gagal menghapus foto rekam medis.', [
                'exception' => $e,
                'medical_photo_id' => $photo->id,
                'medical_record_id' => $photo->medical_record_id,
                'tenant_id' => $photo->tenant_id,
            ]);

            throw $e;
        }

        try {
            Storage::disk('public')->delete($photo->path);
        } catch (Throwable $e) {
            Log::warning('Berkas foto rekam medis tertinggal di disk setelah barisnya dihapus.', [
                'exception' => $e,
                'medical_photo_id' => $photo->id,
                'path' => $photo->path,
                'tenant_id' => $photo->tenant_id,
            ]);
        }

        app(LogAuditAction::class)->handle(
            'medical_record.photo_deleted',
            $photo->medicalRecord,
            auth()->user(),
            [
                'old' => $attributes,
                'tenant_id' => $photo->tenant_id,
            ],
            'Menghapus foto '.$photo->type->value.' pada rekam medis #'.$photo->medical_record_id.'.',
        );

        return $photo;
    }
}
