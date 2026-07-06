<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Http\Requests\StockMovementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function store(StockMovementRequest $request, Product $product): JsonResponse
    {
        $this->authorize('create', StockMovement::class);

        $data = $request->validated();

        $movement = app(StockService::class)->adjust(
            $product,
            StockMovementType::from($data['type']),
            (int) $data['quantity'],
            $data['note'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $movement->id,
                'product_id' => $movement->product_id,
                'type' => $movement->type,
                'type_label' => $movement->type?->label(),
                'quantity' => $movement->quantity,
                'balance_after' => $movement->balance_after,
                'note' => $movement->note,
                'created_at' => $movement->created_at?->toIso8601String(),
            ],
            'meta' => ['message' => __('inventory.recorded')],
        ], 201);
    }

    public function indexByProduct(Product $product): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);

        $page = $product->stockMovements()->latest('created_at')->paginate();

        return response()->json([
            'data' => collect($page->items())->map(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->type,
                'type_label' => $movement->type?->label(),
                'quantity' => $movement->quantity,
                'balance_after' => $movement->balance_after,
                'note' => $movement->note,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
