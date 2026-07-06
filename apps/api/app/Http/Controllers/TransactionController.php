<?php

namespace App\Http\Controllers;

use App\Actions\CancelTransactionAction;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\TransactionRequest;
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

        $query = Transaction::query()->with('patient');

        if ($params['search']) {
            $query->where('invoice_number', 'like', '%'.$params['search'].'%');
        }
        if (($params['filters']['payment_status'] ?? null)) {
            $query->where('payment_status', $params['filters']['payment_status']);
        }
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
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

        return response()->json([
            'data' => new TransactionResource($transaction->load('items', 'patient')),
            'meta' => [],
        ]);
    }

    public function cancel(Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        app(CancelTransactionAction::class)->handle($transaction);

        return response()->json([
            'data' => new TransactionResource($transaction->fresh()->load('items', 'patient')),
            'meta' => ['message' => __('pos.cancelled')],
        ]);
    }
}
