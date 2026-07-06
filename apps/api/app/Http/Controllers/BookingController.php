<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingOverlapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $params = $this->dataTableParams($request);

        $query = Booking::query()->with(['patient', 'service', 'assignee']);

        if (($params['filters']['status'] ?? null)) {
            $query->where('status', $params['filters']['status']);
        }
        if (($params['filters']['assignee_id'] ?? null)) {
            $query->where('assignee_id', $params['filters']['assignee_id']);
        }
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->orderBy('start_at', 'desc');
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => BookingResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(BookingRequest $request): JsonResponse
    {
        $this->authorize('create', Booking::class);

        $booking = Booking::create([
            ...$request->validated(),
            'status' => BookingStatus::Pending,
        ]);

        $warnings = app(BookingOverlapService::class)->detect($booking);

        return response()->json([
            'data' => new BookingResource($booking->load('patient', 'service', 'assignee')),
            'meta' => [
                'overlap_warnings' => $warnings,
                'message' => __('booking.created'),
            ],
        ], 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        return response()->json([
            'data' => new BookingResource($booking->load('patient', 'service', 'assignee')),
            'meta' => [],
        ]);
    }

    public function update(BookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $booking->update($request->validated());

        return response()->json([
            'data' => new BookingResource($booking->load('patient', 'service', 'assignee')),
            'meta' => ['message' => __('booking.updated')],
        ]);
    }

    public function updateStatus(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_column(BookingStatus::cases(), 'value'))],
        ]);

        $target = BookingStatus::from($validated['status']);

        if (! $booking->status->canTransitionTo($target)) {
            abort(422, __('clinic.invalid_transition'));
        }

        $booking->update([
            'status' => $target,
            'status_changed_at' => now(),
        ]);

        return response()->json([
            'data' => new BookingResource($booking->load('patient', 'service', 'assignee')),
            'meta' => ['message' => __('booking.status_updated')],
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'view' => ['nullable', Rule::in(['day', 'week'])],
        ]);

        $bookings = Booking::query()
            ->with(['patient', 'service', 'assignee'])
            ->where('status', '!=', BookingStatus::Cancelled)
            ->whereBetween('start_at', [$validated['from'], $validated['to']])
            ->orderBy('start_at')
            ->orderBy('assignee_id')
            ->get();

        $data = $bookings->map(fn (Booking $booking): array => [
            'id' => $booking->id,
            'patient_name' => $booking->patient?->name,
            'service_name' => $booking->service?->name,
            'assignee_id' => $booking->assignee_id,
            'assignee_name' => $booking->assignee?->name,
            'start_at' => $booking->start_at?->toIso8601String(),
            'end_at' => $booking->end_at?->toIso8601String(),
            'status' => $booking->status,
        ])->all();

        return response()->json(['data' => $data, 'meta' => []]);
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $this->authorize('delete', $booking);

        $booking->delete();

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('booking.deleted')],
        ]);
    }
}
