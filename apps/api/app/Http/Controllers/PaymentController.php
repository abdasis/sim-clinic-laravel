<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function store(PaymentRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        $result = $this->payments->record($transaction, $request->validated());

        return response()->json([
            'data' => new TransactionResource($transaction->fresh()->load('items', 'patient', 'payments')),
            'meta' => [
                'payment_status' => $result['payment_status'],
                'overpaid' => $result['overpaid'],
                'outstanding' => $result['outstanding'],
                'message' => __('pos.paid'),
            ],
        ]);
    }
}
