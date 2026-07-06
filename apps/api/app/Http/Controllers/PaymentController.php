<?php

namespace App\Http\Controllers;

use App\Actions\PayTransactionAction;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        $result = app(PayTransactionAction::class)->handle($transaction, $request->validated());

        return response()->json([
            'data' => new TransactionResource($transaction->fresh()->load('items', 'patient')),
            'meta' => [
                'payment_status' => $result['payment_status'],
                'overpaid' => $result['overpaid'],
                'message' => __('pos.paid'),
            ],
        ]);
    }
}
