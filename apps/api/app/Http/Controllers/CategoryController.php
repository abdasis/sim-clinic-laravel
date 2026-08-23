<?php

namespace App\Http\Controllers;

use App\Enums\CategorizableType;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD kategori terpusat. Daftarnya selalu disaring per jenis — kategori
 * layanan dan produk tidak pernah relevan dalam satu layar yang sama.
 */
class CategoryController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $params = $this->dataTableParams($request);

        $query = Category::query()->withCount('categorizables');

        // Jenis wajib: tanpa penyaring, formulir layanan akan menawarkan
        // kategori pengeluaran.
        $type = $params['filters']['type'] ?? null;

        if ($type !== null && CategorizableType::tryFrom($type) !== null) {
            $query->where('type', $type);
        }

        if ($params['search']) {
            Search::apply($query, ['name'], $params['search']);
        }

        // Formulir hanya boleh menawarkan kategori aktif; halaman kelola
        // meminta arsipnya secara eksplisit.
        $status = $params['filters']['status'] ?? null;

        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! $this->applyAllowedSort($query, $params, ['name', 'description', 'status', 'created_at'])) {
            $query->orderBy('name');
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => CategoryResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(CategoryRequest $request, CategoryService $categories): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $categories->create($request->validated());

        return response()->json([
            'data' => new CategoryResource($category),
            'meta' => ['message' => __('category.created')],
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return response()->json([
            'data' => new CategoryResource($category->loadCount('categorizables')),
            'meta' => [],
        ]);
    }

    public function update(CategoryRequest $request, Category $category, CategoryService $categories): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $categories->update($category, $request->validated());

        return response()->json([
            'data' => new CategoryResource($category),
            'meta' => ['message' => __('category.updated')],
        ]);
    }

    /** Arsipkan — item yang sudah memakainya tetap menampilkan namanya. */
    public function destroy(Category $category, CategoryService $categories): JsonResponse
    {
        $this->authorize('delete', $category);

        $categories->archive($category);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('category.archived')],
        ]);
    }

    public function forceDestroy(Category $category, CategoryService $categories): JsonResponse
    {
        $this->authorize('delete', $category);

        $categories->delete($category);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('category.deleted')],
        ]);
    }
}
