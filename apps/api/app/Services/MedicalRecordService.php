<?php

namespace App\Services;

use App\Actions\MedicalRecord\AddTreatmentRecordAction;
use App\Actions\MedicalRecord\CreateMedicalRecordAction;
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
 * Use case rekam medis. Rekam medis hanya boleh ditulis untuk kunjungan
 * yang benar-benar sudah selesai, dan satu kunjungan satu rekam medis.
 */
class MedicalRecordService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $author, array $data): MedicalRecord
    {
        $booking = Booking::findOrFail($data['booking_id']);

        if ($booking->status !== BookingStatus::Done) {
            abort(422, __('medical_record.booking_not_done'));
        }

        if (MedicalRecord::where('booking_id', $booking->id)->exists()) {
            abort(422, __('medical_record.already_exists'));
        }

        return app(CreateMedicalRecordAction::class)->handle($booking, $author, $data);
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

    public function addPhoto(MedicalRecord $record, UploadedFile $file, MedicalPhotoType $type): MedicalPhoto
    {
        return app(UploadMedicalPhotoAction::class)->handle($record, $file, $type);
    }
}
