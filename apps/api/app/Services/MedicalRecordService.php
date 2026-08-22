<?php

namespace App\Services;

use App\Actions\MedicalRecord\AddTreatmentRecordAction;
use App\Actions\MedicalRecord\CreateMedicalRecordAction;
use App\Actions\MedicalRecord\DeleteMedicalPhotoAction;
use App\Actions\MedicalRecord\SoftDeleteMedicalRecordAction;
use App\Actions\MedicalRecord\UpdateMedicalRecordAction;
use App\Actions\MedicalRecord\UploadMedicalPhotoAction;
use App\Enums\BookingStatus;
use App\Enums\MedicalPhotoType;
use App\Models\Booking;
use App\Models\MedicalPhoto;
use App\Models\MedicalRecord;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Use case rekam medis.
 *
 * Catatan boleh lahir dari dua jalur: dari kunjungan yang sudah selesai
 * (satu kunjungan tetap satu rekam medis), atau langsung dari pasien untuk
 * kunjungan walk-in yang tidak pernah dijadwalkan.
 */
class MedicalRecordService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $author, array $data): MedicalRecord
    {
        return app(CreateMedicalRecordAction::class)->handle(
            $this->resolveBooking($data),
            $author,
            $data,
        );
    }

    /**
     * Kunjungan yang ditagihkan catatan ini, atau null untuk walk-in.
     *
     * Penjagaan lama hanya berlaku pada jalur berbooking: kunjungan harus
     * benar-benar selesai, dan satu kunjungan tetap satu rekam medis.
     * Jalur tanpa booking sengaja tidak dibatasi — pasien yang datang
     * berulang tanpa janji adalah hal wajar, dan menolak catatan keduanya
     * justru menghilangkan riwayat (issue #319).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveBooking(array $data): ?Booking
    {
        if (blank($data['booking_id'] ?? null)) {
            return null;
        }

        $booking = Booking::findOrFail($data['booking_id']);

        if ($booking->status !== BookingStatus::Done) {
            abort(422, __('medical_record.booking_not_done'));
        }

        if (MedicalRecord::where('booking_id', $booking->id)->exists()) {
            abort(422, __('medical_record.already_exists'));
        }

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addTreatment(MedicalRecord $record, array $data): TreatmentRecord
    {
        $serviceName = $data['service_name'] ?? null;

        if (! empty($data['service_id'])) {
            $serviceName = Service::findOrFail($data['service_id'])->name;
        }

        return app(AddTreatmentRecordAction::class)->handle($record, $data, $serviceName);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MedicalRecord $record, array $data): MedicalRecord
    {
        return app(UpdateMedicalRecordAction::class)->handle($record, $data);
    }

    public function softDelete(MedicalRecord $record): MedicalRecord
    {
        return app(SoftDeleteMedicalRecordAction::class)->handle($record);
    }

    public function addPhoto(MedicalRecord $record, UploadedFile $file, MedicalPhotoType $type): MedicalPhoto
    {
        return app(UploadMedicalPhotoAction::class)->handle($record, $file, $type);
    }

    public function deletePhoto(MedicalPhoto $photo): MedicalPhoto
    {
        return app(DeleteMedicalPhotoAction::class)->handle($photo);
    }
}
