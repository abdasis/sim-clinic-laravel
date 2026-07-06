<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        $params = $this->dataTableParams($request);

        $query = Patient::query();

        if ($params['search']) {
            $search = $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

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

    public function store(PatientRequest $request): JsonResponse
    {
        $this->authorize('create', Patient::class);

        $patient = Patient::create($request->validated());

        $duplicate = Patient::where('phone', $patient->phone)
            ->where('id', '!=', $patient->id)
            ->first();

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

    public function update(PatientRequest $request, Patient $patient): JsonResponse
    {
        $this->authorize('update', $patient);

        $patient->update($request->validated());

        return response()->json([
            'data' => new PatientResource($patient),
            'meta' => ['message' => __('patient.updated')],
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
