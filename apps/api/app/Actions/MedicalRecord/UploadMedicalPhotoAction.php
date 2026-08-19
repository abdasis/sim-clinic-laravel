<?php

namespace App\Actions\MedicalRecord;

use App\Actions\LogAuditAction;
use App\Enums\MedicalPhotoType;
use App\Models\MedicalPhoto;
use App\Models\MedicalRecord;
use Illuminate\Http\UploadedFile;

/**
 * Simpan foto rekam medis ke disk `public` (R3):
 * path `medical-photos/{tenant_id}/{record_id}/{file}`, buat record MedicalPhoto.
 */
class UploadMedicalPhotoAction
{
    public function handle(MedicalRecord $record, UploadedFile $file, MedicalPhotoType $type): MedicalPhoto
    {
        $directory = "medical-photos/{$record->tenant_id}/{$record->id}";

        $path = $file->store($directory, 'public');

        $photo = MedicalPhoto::create([
            'tenant_id' => $record->tenant_id,
            'medical_record_id' => $record->id,
            'type' => $type,
            'path' => $path,
        ]);

        app(LogAuditAction::class)->handle(
            'medical_record.photo_uploaded',
            $photo,
            auth()->user(),
            [
                'new' => $photo->getAttributes(),
                'tenant_id' => $record->tenant_id,
                'original_name' => $file->getClientOriginalName(),
                'size_bytes' => $file->getSize(),
            ],
            'Mengunggah foto '.$type->value.' pada rekam medis #'.$record->id.'.',
        );

        return $photo;
    }
}
