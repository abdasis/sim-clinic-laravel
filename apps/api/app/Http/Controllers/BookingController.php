<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\BookingRequest;
use App\Http\Requests\BookingScheduleRequest;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $params = $this->dataTableParams($request);

        // Rekam medis ikut dimuat supaya `has_medical_record` di resource
        // berisi keadaan sebenarnya. Tanpa ini flag itu selalu false, dan
        // pemilih kunjungan di form rekam medis tetap menawarkan kunjungan
        // yang catatannya sudah ada — baru ditolak setelah disimpan.
        $query = Booking::query()->with(['patient', 'service', 'assignee', 'medicalRecord']);

        if (($params['filters']['status'] ?? null)) {
            $query->where('status', $params['filters']['status']);
        }
        if (($params['filters']['assignee_id'] ?? null)) {
            $query->where('assignee_id', $params['filters']['assignee_id']);
        }
        // Kasir menautkan transaksi ke kunjungan pasien yang sedang dilayani,
        // jadi daftarnya perlu bisa dipersempit ke satu pasien.
        if (($params['filters']['patient_id'] ?? null)) {
            $query->where('patient_id', $params['filters']['patient_id']);
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

    public function store(BookingRequest $request, BookingService $service): JsonResponse
    {
        $this->authorize('create', Booking::class);

        [$booking, $warnings] = $service->create($request->validated());

        return response()->json([
            'data' => new BookingResource($booking),
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
            'data' => new BookingResource($booking->load('patient', 'service', 'assignee', 'medicalRecord')),
            'meta' => [],
        ]);
    }

    public function update(BookingRequest $request, Booking $booking, BookingService $service): JsonResponse
    {
        $this->authorize('update', $booking);

        [$booking, $warnings] = $service->update($booking, $request->validated());

        return response()->json([
            'data' => new BookingResource($booking),
            'meta' => [
                'overlap_warnings' => $warnings,
                'message' => __('booking.updated'),
            ],
        ]);
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking, BookingService $service): JsonResponse
    {
        $this->authorize('update', $booking);

        $booking = $service->changeStatus($booking, $request->validated('status'));

        return response()->json([
            'data' => new BookingResource($booking),
            'meta' => ['message' => __('booking.status_updated')],
        ]);
    }

    public function schedule(BookingScheduleRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $validated = $request->validated();

        // `to` datang sebagai tanggal (yyyy-MM-dd) → parse jadi tengah malam,
        // jadi booking sore/malam di hari terakhir rentang lolos filter.
        $to = Carbon::parse($validated['to'])->endOfDay();

        $bookings = Booking::query()
            ->with(['patient', 'service', 'assignee'])
            ->where('status', '!=', BookingStatus::Cancelled)
            ->whereBetween('start_at', [$validated['from'], $to])
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

    public function destroy(Booking $booking, BookingService $service): JsonResponse
    {
        $this->authorize('delete', $booking);

        $service->delete($booking);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('booking.deleted')],
        ]);
    }
}
