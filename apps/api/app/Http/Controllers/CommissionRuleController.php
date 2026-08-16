<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommissionRuleRequest;
use App\Http\Resources\CommissionRuleResource;
use App\Models\CommissionRule;
use App\Models\Expense;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aturan fee terapis menempel pada modul pengeluaran: fee yang dihitung di
 * sini berakhir sebagai pengeluaran, jadi izinnya pun mengikuti.
 */
class CommissionRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        return response()->json([
            'data' => CommissionRuleResource::collection(
                // Hanya tarif yang masih berlaku. Versi lama tetap tersimpan
                // untuk menghitung periode yang sudah lewat, tapi menampilkan
                // semuanya membuat daftar penuh duplikat bernama sama.
                CommissionRule::query()
                    ->current()
                    ->with('therapist')
                    ->orderBy('type')
                    ->orderBy('min_revenue')
                    ->get()
            ),
            'meta' => [],
        ]);
    }

    public function store(CommissionRuleRequest $request, CommissionService $commissions): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $rule = $commissions->save($request->validated());

        return response()->json([
            'data' => new CommissionRuleResource($rule),
            'meta' => ['message' => __('commission.created')],
        ], 201);
    }

    public function update(
        CommissionRuleRequest $request,
        CommissionRule $commissionRule,
        CommissionService $commissions,
    ): JsonResponse {
        $this->authorize('update', Expense::class);

        $rule = $commissions->save($request->validated(), $commissionRule);

        return response()->json([
            'data' => new CommissionRuleResource($rule),
            'meta' => ['message' => __('commission.updated')],
        ]);
    }

    public function destroy(CommissionRule $commissionRule, CommissionService $commissions): JsonResponse
    {
        $this->authorize('delete', Expense::class);

        $commissions->delete($commissionRule);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('commission.deleted')],
        ]);
    }

    /**
     * Pratinjau fee satu periode. Tidak menyimpan apa pun — admin yang
     * memutuskan membukukannya sebagai pengeluaran.
     */
    public function calculate(Request $request, CommissionService $commissions): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json([
            'data' => $commissions->calculate($validated['from'], $validated['to']),
            'meta' => ['from' => $validated['from'], 'to' => $validated['to']],
        ]);
    }
}
