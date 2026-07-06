<?php

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
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
        if (($params['filters']['status'] ?? null)) {
            $query->where('status', $params['filters']['status']);
        }
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

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

    public function store(ProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = Product::create($request->validated());

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

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return response()->json([
            'data' => new ProductResource($product),
            'meta' => ['message' => __('product.updated')],
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->update(['status' => ServiceStatus::Archived]);

        return response()->json([
            'data' => new ProductResource($product->fresh()),
            'meta' => ['message' => __('product.archived')],
        ]);
    }
}
