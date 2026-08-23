<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientService;
use App\Support\PatientReferences;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        $params = $this->dataTableParams($request);

        // Nama pembawa ikut ditampilkan di daftar; tanpa eager load,
        // resource menembak satu kueri per baris.
        $query = Patient::query()->with('referrer:id,name');

        Search::apply($query, ['name', 'whatsapp'], $params['search']);
        if (! $this->applyAllowedSort($query, $params, ['name', 'whatsapp', 'gender', 'birth_date', 'created_at'])) {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        // Jejak seluruh baris halaman ini ditarik sekali, jadi kolom "boleh
        // dihapus" tidak menembak satu kueri per pasien.
        $references = new PatientReferences;
        $references->preload(collect($page->items()));
        $request->attributes->set('patient_references', $references);

        return response()->json([
            'data' => PatientResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(PatientRequest $request, PatientService $service): JsonResponse
    {
        $this->authorize('create', Patient::class);

        [$patient, $duplicate] = $service->create($request->validated());

        return response()->json([
            'data' => new PatientResource($patient),
            'meta' => [
                'duplicate_warning' => $duplicate !== null,
                'duplicate_patient_id' => $duplicate?->id,
                'message' => __('patient.created'),
            ],
        ], 201);
    }

    public function show(Patient $patient): JsonResponse
    {
        $this->authorize('view', $patient);

        return response()->json(['data' => new PatientResource($patient), 'meta' => []]);
    }

    public function update(PatientRequest $request, Patient $patient, PatientService $service): JsonResponse
    {
        $this->authorize('update', $patient);

        [$patient, $duplicate] = $service->update($patient, $request->validated());

        return response()->json([
            'data' => new PatientResource($patient),
            'meta' => [
                'duplicate_warning' => $duplicate !== null,
                'duplicate_patient_id' => $duplicate?->id,
                'message' => __('patient.updated'),
            ],
        ]);
    }

    public function destroy(Patient $patient, PatientService $service): JsonResponse
    {
        $this->authorize('delete', $patient);

        $deleted = $service->delete($patient);

        return response()->json([
            'data' => new PatientResource($patient),
            'meta' => [
                'deleted' => $deleted,
                'message' => $deleted ? __('patient.deleted') : __('patient.archived'),
            ],
        ]);
    }

    public function history(Patient $patient): JsonResponse
    {
        $this->authorize('view', $patient);

        $bookings = $patient->bookings()->with(['service', 'assignee'])->get()
            ->map(fn ($booking) => [
                'date' => $booking->start_at?->toIso8601String(),
                'service_name' => $booking->service?->name,
                'status' => $booking->status?->value,
                'assignee_name' => $booking->assignee?->name,
                'type' => 'booking',
            ]);

        $treatments = $patient->medicalRecords()->with(['treatmentRecords', 'author'])->get()
            ->flatMap(fn ($record) => $record->treatmentRecords->map(fn ($treatment) => [
                'date' => $treatment->created_at?->toIso8601String(),
                'service_name' => $treatment->service_name,
                'status' => 'done',
                'assignee_name' => $record->author?->name,
                'type' => 'treatment',
            ]));

        $history = $bookings->concat($treatments)
            ->sortBy('date')
            ->values()
            ->all();

        return response()->json(['data' => $history, 'meta' => []]);
    }
}
