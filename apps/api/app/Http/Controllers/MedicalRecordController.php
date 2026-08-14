<?php

namespace App\Http\Controllers;

use App\Enums\MedicalPhotoType;
use App\Http\Requests\MedicalPhotoRequest;
use App\Http\Requests\MedicalRecordRequest;
use App\Http\Requests\TreatmentRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Services\MedicalRecordService;
use Illuminate\Http\JsonResponse;

class MedicalRecordController extends Controller
{
    public function store(MedicalRecordRequest $request, MedicalRecordService $service): JsonResponse
    {
        $this->authorize('create', MedicalRecord::class);

        $record = $service->create($request->user(), $request->validated());

        return response()->json([
            'data' => new MedicalRecordResource($record),
            'meta' => ['message' => __('medical_record.created')],
        ], 201);
    }

    public function addTreatment(TreatmentRecordRequest $request, MedicalRecord $medicalRecord, MedicalRecordService $service): JsonResponse
    {
        $this->authorize('update', $medicalRecord);

        $service->addTreatment($medicalRecord, $request->validated());

        return response()->json([
            'data' => new MedicalRecordResource($medicalRecord->fresh(['treatmentRecords', 'medicalPhotos', 'author'])),
            'meta' => ['message' => __('medical_record.treatment_added')],
        ]);
    }

    public function addPhoto(MedicalPhotoRequest $request, MedicalRecord $medicalRecord, MedicalRecordService $service): JsonResponse
    {
        $this->authorize('update', $medicalRecord);

        $photo = $service->addPhoto(
            $medicalRecord,
            $request->file('file'),
            MedicalPhotoType::from($request->validated('type')),
        );

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
