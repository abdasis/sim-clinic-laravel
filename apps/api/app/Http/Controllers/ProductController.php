<?php

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\ImportProductsRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ImportService;
use App\Services\ProductService;
use App\Support\PromoPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $params = $this->dataTableParams($request);

        // Tanpa eager load, resource memanggil relasi kategori per baris.
        $query = Product::query()->with(['categories', 'unit']);

        if ($params['search']) {
            $query->where('name', 'like', '%'.$params['search'].'%');
        }
        // Master produk default hanya menampilkan yang aktif; arsip diminta eksplisit.
        $status = $params['filters']['status'] ?? ServiceStatus::Active->value;

        // Penyaring status bisa memuat lebih dari satu nilai (mis.
        // "active,archived") karena kendalinya di tabel bersifat pilih-banyak.
        if ($status !== 'all') {
            $query->whereIn('status', array_filter(explode(',', (string) $status)));
        }

        // Kasir hanya boleh melihat barang jual; inventaris melihat semuanya.
        $type = $params['filters']['type'] ?? null;

        if ($type !== null && $type !== 'all') {
            $query->where('type', $type);
        }

        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        // Satu pencari promo dipakai bersama seluruh baris halaman ini, jadi
        // katalog tidak menembak satu kueri promo per baris.
        $pricing = new PromoPricing;
        $pricing->preload(collect($page->items()));
        $request->attributes->set('promo_pricing', $pricing);

        return response()->json([
            'data' => ProductResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(ProductRequest $request, ProductService $service): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $service->create($request->validated());

        return response()->json([
            'data' => new ProductResource($product),
            'meta' => ['message' => __('product.created')],
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json(['data' => new ProductResource($product), 'meta' => []]);
    }

    public function update(ProductRequest $request, Product $product, ProductService $service): JsonResponse
    {
        $this->authorize('update', $product);

        $service->update($product, $request->validated());

        return response()->json([
            'data' => new ProductResource($product),
            'meta' => ['message' => __('product.updated')],
        ]);
    }

    /**
     * Hapus permanen, berbeda dari destroy yang mengarsipkan. Dipisah jadi
     * rute sendiri supaya tindakan yang tidak bisa dibatalkan tidak pernah
     * terpanggil tanpa sengaja oleh tombol hapus yang lama.
     */
    public function forceDestroy(Product $product, ProductService $service): JsonResponse
    {
        $this->authorize('delete', $product);

        $service->delete($product);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('product.deleted')],
        ]);
    }

    public function destroy(Product $product, ProductService $service): JsonResponse
    {
        $this->authorize('delete', $product);

        $service->archive($product);

        return response()->json([
            'data' => new ProductResource($product->fresh()),
            'meta' => ['message' => __('product.archived')],
        ]);
    }

    /**
     * Impor massal dari template. Baris bermasalah dikembalikan apa adanya
     * supaya pengisi tahu baris mana yang perlu dibetulkan — baris lainnya
     * tetap masuk.
     */
    public function import(ImportProductsRequest $request, ImportService $imports): JsonResponse
    {
        $result = $imports->importProducts($request->file('file'), $request->user());

        return response()->json([
            'data' => $result,
            'meta' => ['message' => __('import.result_title')],
        ]);
    }

    /** Template berisi contoh isian dan daftar kategori klinik ini. */
    public function importTemplate(ImportService $imports): StreamedResponse
    {
        $this->authorize('viewAny', Product::class);

        $spreadsheet = $imports->template('product');

        return new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-produk.xlsx"',
        ]);
    }
}
