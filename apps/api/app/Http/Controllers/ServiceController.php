<?php

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\ImportServicesRequest;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ImportService;
use App\Services\ServiceCatalogService;
use App\Support\PromoPricing;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $params = $this->dataTableParams($request);

        // Tanpa eager load, resource memanggil relasi kategori per baris.
        $query = Service::query()->with('categories');

        Search::apply($query, 'name', $params['search']);

        // Katalog default hanya menampilkan layanan aktif; arsip diminta eksplisit.
        $status = $params['filters']['status'] ?? ServiceStatus::Active->value;

        // Penyaring status bisa memuat lebih dari satu nilai (mis.
        // "active,archived") karena kendalinya di tabel bersifat pilih-banyak.
        if ($status !== 'all') {
            $query->whereIn('status', array_filter(explode(',', (string) $status)));
        }
        // Abjad sebagai bawaan, bukan yang terbaru: katalog dibaca untuk
        // mencari satu nama tertentu - di kasir maupun di layar master - dan
        // urutan waktu pembuatan tidak menolong pencarian itu sama sekali.
        $this->applyCatalogSort($query, $params);

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        // Satu pencari promo dipakai bersama seluruh baris halaman ini, jadi
        // katalog tidak menembak satu kueri promo per baris.
        $pricing = new PromoPricing;
        $pricing->preload(collect($page->items()));
        $request->attributes->set('promo_pricing', $pricing);

        return response()->json([
            'data' => ServiceResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(ServiceRequest $request, ServiceCatalogService $catalog): JsonResponse
    {
        $this->authorize('create', Service::class);

        $service = $catalog->create($request->validated());

        return response()->json([
            'data' => new ServiceResource($service),
            'meta' => ['message' => __('service.created')],
        ], 201);
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return response()->json(['data' => new ServiceResource($service), 'meta' => []]);
    }

    public function update(ServiceRequest $request, Service $service, ServiceCatalogService $catalog): JsonResponse
    {
        $this->authorize('update', $service);

        $catalog->update($service, $request->validated());

        return response()->json([
            'data' => new ServiceResource($service),
            'meta' => ['message' => __('service.updated')],
        ]);
    }

    /**
     * Hapus permanen, berbeda dari destroy yang mengarsipkan. Dipisah jadi
     * rute sendiri supaya tindakan yang tidak bisa dibatalkan tidak pernah
     * terpanggil tanpa sengaja oleh tombol hapus yang lama.
     */
    public function forceDestroy(Service $service, ServiceCatalogService $catalog): JsonResponse
    {
        $this->authorize('delete', $service);

        $catalog->delete($service);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('service.deleted')],
        ]);
    }

    public function destroy(Service $service, ServiceCatalogService $catalog): JsonResponse
    {
        $this->authorize('delete', $service);

        $catalog->archive($service);

        return response()->json([
            'data' => new ServiceResource($service->fresh()),
            'meta' => ['message' => __('service.archived')],
        ]);
    }

    /**
     * Impor massal dari template. Baris bermasalah dikembalikan apa adanya
     * supaya pengisi tahu baris mana yang perlu dibetulkan — baris lainnya
     * tetap masuk.
     */
    public function import(ImportServicesRequest $request, ImportService $imports): JsonResponse
    {
        // Satu berkas bisa membuat ratusan layanan sekaligus, jadi izinnya
        // diperiksa di sini juga, bukan hanya di FormRequest.
        $this->authorize('create', Service::class);

        $result = $imports->importServices($request->file('file'), $request->user());

        return response()->json([
            'data' => $result,
            'meta' => ['message' => __('import.result_title')],
        ]);
    }

    /** Template berisi contoh isian dan daftar kategori klinik ini. */
    public function importTemplate(ImportService $imports): StreamedResponse
    {
        $this->authorize('viewAny', Service::class);

        $spreadsheet = $imports->template('service');

        return new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-layanan.xlsx"',
        ]);
    }
}
