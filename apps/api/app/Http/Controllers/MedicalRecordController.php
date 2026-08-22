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
use App\Services\MedicalRecordService;
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
            // Transaksinya ikut dimuat karena kolom OBT/HCP dan harga di
            // tabel riwayat berasal dari nota, bukan dari catatan medis.
            ->with([
                'treatmentRecords', 'medicalPhotos', 'author', 'patient',
                'booking.transaction.items', 'booking.transaction.performers',
            ])
            ->paginate($perPage, ['*'], 'page', max((int) $request->integer('page', 1), 1));

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
