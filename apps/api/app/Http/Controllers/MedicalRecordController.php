<?php

namespace App\Http\Controllers;

use App\Enums\MedicalPhotoType;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\MedicalPhotoRequest;
use App\Http\Requests\MedicalRecordRequest;
use App\Http\Requests\TreatmentRecordRequest;
use App\Http\Resources\MedicalPhotoResource;
use App\Http\Resources\MedicalRecordResource;
use App\Models\MedicalPhoto;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Transaction;
use App\Services\MedicalRecordService;
use App\Support\PatientPurchases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'data' => new MedicalPhotoResource($photo),
            'meta' => ['message' => __('medical_record.photo_added')],
        ], 201);
    }

    /**
     * Buang satu foto klinis dari rekam medis.
     *
     * Fotonya diikat ke rekam medis lewat scoped binding di rute, jadi id
     * milik catatan lain berhenti sebagai 404 sebelum sampai ke sini.
     */
    public function deletePhoto(
        MedicalRecord $medicalRecord,
        MedicalPhoto $medicalPhoto,
        MedicalRecordService $service,
    ): JsonResponse {
        $this->authorize('update', $medicalRecord);

        $service->deletePhoto($medicalPhoto);

        return response()->json([
            'data' => new MedicalPhotoResource($medicalPhoto),
            'meta' => ['message' => __('medical_record.photo_deleted')],
        ]);
    }

    /**
     * Riwayat rekam medis satu pasien, urut kronologis.
     */
    public function patientRecords(Patient $patient, Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicalRecord::class);

        // Diurutkan menurut waktu kunjungannya, bukan waktu catatannya ditulis.
        // Dokter kerap merampungkan catatan setelah pasien pulang, kadang
        // keesokan harinya — kalau riwayatnya diurutkan dari waktu tulis,
        // perkembangan pasien terbaca dengan urutan yang salah.
        //
        // Digabung lewat leftJoin, bukan subquery berkorelasi di ORDER BY:
        // bentuk yang lama menjalankan satu subquery untuk setiap baris yang
        // diurutkan. Left, bukan inner — catatan yang ditulis tanpa booking
        // tidak boleh hilang dari riwayat, dan waktu tulisnya yang dipakai
        // sebagai gantinya.
        //
        // Urutannya tetap dari kunjungan terlama ke terbaru, sama seperti
        // sebelumnya — itu urutan yang dibaca dokter, dan mengubahnya demi
        // paginasi berarti menukar kebenaran dengan kecepatan. Yang berubah
        // hanya cara datangnya: riwayat panjang tidak lagi dimuat sekaligus
        // berikut seluruh foto dan tindakannya.
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $page = MedicalRecord::query()
            ->where('medical_records.patient_id', $patient->id)
            ->leftJoin('bookings', 'bookings.id', '=', 'medical_records.booking_id')
            ->select('medical_records.*')
            ->orderBy(DB::raw('COALESCE(bookings.start_at, medical_records.created_at)'))
            ->orderBy('medical_records.id')
            ->with(['treatmentRecords', 'medicalPhotos', 'author', 'patient', 'booking'])
            ->paginate($perPage, ['*'], 'page', max((int) $request->integer('page', 1), 1));

        // Belanja pasien ditautkan sekali untuk seluruh baris halaman ini.
        // Sumbernya pasien dan tanggal, bukan booking: kasir hanya wajib
        // memilih pasien, jadi produk yang dibeli tanpa memilih booking dulu
        // tidak pernah sampai ke rekam medisnya.
        $purchases = new PatientPurchases;
        $purchases->preload($patient->id, collect($page->items()));
        $request->attributes->set('patient_purchases', $purchases);

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

    /**
     * Seluruh produk yang pernah dibeli pasien, urut dari yang terbaru.
     *
     * Berdiri sendiri di samping riwayat kunjungan karena tidak semua
     * pembelian berpapasan dengan kunjungan: pasien boleh menebus skincare
     * tanpa treatment apa pun hari itu, dan pembelian seperti itu tidak
     * menempel di baris rekam medis mana pun. Yang perlu dibaca dokter tetap
     * sama — apa yang sedang dipakai pasien di rumah.
     */
    public function patientPurchases(Patient $patient, Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicalRecord::class);

        $perPage = min(max((int) $request->integer('per_page', 30), 1), 100);

        $page = Transaction::query()
            ->with('items')
            ->where('patient_id', $patient->id)
            ->whereNull('cancelled_at')
            // Hanya nota yang benar-benar memuat produk; kunjungan yang isinya
            // tindakan saja sudah terbaca di tabel riwayat.
            ->whereHas('items', fn ($query) => $query->whereNotNull('product_id'))
            ->orderByDesc(DB::raw('COALESCE(issued_at, created_at)'))
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max((int) $request->integer('page', 1), 1));

        $data = collect($page->items())->map(fn (Transaction $transaction): array => [
            'transaction_id' => $transaction->id,
            'invoice_number' => $transaction->invoice_number,
            'purchased_at' => ($transaction->issued_at ?? $transaction->created_at)?->toIso8601String(),
            // Ditandai supaya layar bisa menjelaskan pembelian yang berdiri
            // sendiri, bukan diam-diam menampilkannya seolah bagian kunjungan.
            'linked_to_visit' => $transaction->booking_id !== null,
            'items' => $transaction->items
                ->filter(fn ($item) => $item->product_id !== null)
                ->map(fn ($item) => [
                    'name' => $item->name,
                    'qty' => (int) $item->qty,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                ])->values(),
        ])->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
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

        if (! $this->applyAllowedSort($query, $params, ['created_at', 'updated_at'])) {
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
