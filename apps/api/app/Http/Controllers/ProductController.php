<?php

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\PromoPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $params = $this->dataTableParams($request);

        $query = Product::query();

        if ($params['search']) {
            $query->where('name', 'like', '%'.$params['search'].'%');
        }
        // Master produk default hanya menampilkan yang aktif; arsip diminta eksplisit.
        $status = $params['filters']['status'] ?? ServiceStatus::Active->value;

        if ($status !== 'all') {
            $query->where('status', $status);
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

    public function destroy(Product $product, ProductService $service): JsonResponse
    {
        $this->authorize('delete', $product);

        $service->archive($product);

        return response()->json([
            'data' => new ProductResource($product->fresh()),
            'meta' => ['message' => __('product.archived')],
        ]);
    }
}
