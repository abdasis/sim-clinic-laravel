<?php

namespace App\Http\Controllers;

use App\Enums\MedicalPhotoType;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\MedicalPhotoRequest;
use App\Http\Requests\MedicalRecordRequest;
use App\Http\Requests\TreatmentRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Services\MedicalRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    use InteractsWithDataTable;

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

    /**
     * Riwayat rekam medis satu pasien, urut kronologis.
     */
    public function patientRecords(Patient $patient): JsonResponse
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $records = MedicalRecord::where('patient_id', $patient->id)
            ->with(['treatmentRecords', 'medicalPhotos', 'author', 'patient'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => MedicalRecordResource::collection($records),
            'meta' => ['total' => $records->count()],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $params = $this->dataTableParams($request);

        $query = MedicalRecord::query()->with(['patient', 'author']);

        if ($params['search']) {
            $search = $params['search'];
            $query->whereHas('patient', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
        }

        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => MedicalRecordResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(MedicalRecord $medicalRecord): JsonResponse
    {
        $this->authorize('view', $medicalRecord);

        return response()->json([
            'data' => new MedicalRecordResource(
                $medicalRecord->load(['treatmentRecords', 'medicalPhotos', 'author', 'patient', 'booking']),
            ),
            'meta' => [],
        ]);
    }

    public function update(
        MedicalRecordRequest $request,
        MedicalRecord $medicalRecord,
        MedicalRecordService $service,
    ): JsonResponse {
        $this->authorize('update', $medicalRecord);

        $service->update($medicalRecord, $request->validated());

        return response()->json([
            'data' => new MedicalRecordResource(
                $medicalRecord->load(['treatmentRecords', 'medicalPhotos', 'author', 'patient']),
            ),
            'meta' => ['message' => __('medical_record.updated')],
        ]);
    }

    public function destroy(MedicalRecord $medicalRecord, MedicalRecordService $service): JsonResponse
    {
        $this->authorize('delete', $medicalRecord);

        $service->softDelete($medicalRecord);

        return response()->json([
            'data' => new MedicalRecordResource($medicalRecord),
            'meta' => ['message' => __('medical_record.deleted')],
        ]);
    }
}
