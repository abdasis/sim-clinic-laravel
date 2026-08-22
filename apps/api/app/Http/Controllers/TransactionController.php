<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\ClinicIdentityResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transaction::class);

        $params = $this->dataTableParams($request);

        // Kasir ikut dimuat karena TransactionResource menyebut namanya di
        // setiap baris; tanpa ini satu halaman berisi 25 transaksi menembak
        // 25 kueri tambahan.
        $query = Transaction::query()->with(['patient', 'cashier:id,name']);

        if ($params['search']) {
            $query->where('invoice_number', 'like', '%'.$params['search'].'%');
        }
        if (($params['filters']['payment_status'] ?? null)) {
            $query->where('payment_status', $params['filters']['payment_status']);
        }
        if (! $this->applyAllowedSort($query, $params, ['invoice_number', 'subtotal', 'paid_amount', 'payment_status', 'issued_at', 'created_at'])) {
            $query->latest();
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => TransactionResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(TransactionRequest $request): JsonResponse
    {
        $this->authorize('create', Transaction::class);

        $transaction = app(TransactionService::class)->create($request->validated());

        return response()->json([
            'data' => new TransactionResource($transaction),
            'meta' => ['message' => __('pos.created')],
        ], 201);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        // Kop nota ikut di meta supaya halaman invoice tidak perlu request
        // kedua hanya untuk tahu nama, alamat, dan logo kliniknya.
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return response()->json([
            'data' => new TransactionResource($transaction->load('items.offeredBy', 'patient', 'cashier', 'payments', 'performers')),
            'meta' => [
                'clinic' => $tenant
                    ? new ClinicIdentityResource($tenant->loadMissing('companyProfile'))
                    : null,
            ],
        ]);
    }

    public function cancel(Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        app(TransactionService::class)->cancel($transaction);

        return response()->json([
            'data' => new TransactionResource($transaction->fresh()->load('items', 'patient')),
            'meta' => ['message' => __('pos.cancelled')],
        ]);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $transaction);

        app(TransactionService::class)->softDelete($transaction);

        return response()->json([
            'data' => new TransactionResource($transaction),
            'meta' => ['message' => __('pos.deleted')],
        ]);
    }
}
