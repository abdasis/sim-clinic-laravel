<?php

namespace App\Http\Controllers;

use App\Actions\UploadMedicalPhotoAction;
use App\Enums\BookingStatus;
use App\Enums\MedicalPhotoType;
use App\Http\Requests\MedicalPhotoRequest;
use App\Http\Requests\MedicalRecordRequest;
use App\Http\Requests\TreatmentRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentRecord;
use Illuminate\Http\JsonResponse;

class MedicalRecordController extends Controller
{
    public function store(MedicalRecordRequest $request): JsonResponse
    {
        $this->authorize('create', MedicalRecord::class);

        $data = $request->validated();

        $booking = Booking::findOrFail($data['booking_id']);

        if ($booking->status !== BookingStatus::Done) {
            abort(422, __('medical_record.booking_not_done'));
        }

        if (MedicalRecord::where('booking_id', $booking->id)->exists()) {
            abort(422, __('medical_record.already_exists'));
        }

        $record = MedicalRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $booking->patient_id,
            'author_id' => $request->user()->id,
            'subjective' => $data['subjective'] ?? null,
            'objective' => $data['objective'] ?? null,
            'assessment' => $data['assessment'] ?? null,
            'plan' => $data['plan'] ?? null,
        ]);

        return response()->json([
            'data' => new MedicalRecordResource($record),
            'meta' => ['message' => __('medical_record.created')],
        ], 201);
    }

    public function addTreatment(TreatmentRecordRequest $request, MedicalRecord $medicalRecord): JsonResponse
    {
        $this->authorize('update', $medicalRecord);

        $data = $request->validated();

        $serviceName = $data['service_name'] ?? null;

        if (! empty($data['service_id'])) {
            $service = Service::findOrFail($data['service_id']);
            $serviceName = $service->name;
        }

        $treatment = TreatmentRecord::create([
            'medical_record_id' => $medicalRecord->id,
            'service_id' => $data['service_id'] ?? null,
            'service_name' => $serviceName,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'data' => new MedicalRecordResource($medicalRecord->fresh(['treatmentRecords', 'medicalPhotos', 'author'])),
            'meta' => ['message' => __('medical_record.treatment_added')],
        ]);
    }

    public function addPhoto(MedicalPhotoRequest $request, MedicalRecord $medicalRecord, UploadMedicalPhotoAction $action): JsonResponse
    {
        $this->authorize('update', $medicalRecord);

        $type = MedicalPhotoType::from($request->validated('type'));

        $photo = $action->handle($medicalRecord, $request->file('file'), $type);

        return response()->json([
            'data' => [
                'id' => $photo->id,
                'type' => $photo->type,
                'path' => $photo->path,
                'url' => $photo->url,
            ],
            'meta' => ['message' => __('medical_record.photo_added')],
        ], 201);
    }

    public function patientTreatments(Patient $patient): JsonResponse
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $records = MedicalRecord::where('patient_id', $patient->id)
            ->with(['treatmentRecords', 'medicalPhotos', 'author'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => MedicalRecordResource::collection($records),
            'meta' => [],
        ]);
    }
}
