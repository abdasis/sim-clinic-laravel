<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $params = $this->dataTableParams($request);

        $query = Expense::query()
            ->with(['recorder', 'categories'])
            ->between($params['filters']['from'] ?? null, $params['filters']['to'] ?? null);

        if ($params['search']) {
            $query->where(fn ($inner) => $inner
                ->where('description', 'like', '%'.$params['search'].'%')
                ->orWhere('note', 'like', '%'.$params['search'].'%'));
        }

        $category = $params['filters']['category'] ?? 'all';

        if ($category !== 'all') {
            // Nilainya kini id kategori, bukan nilai enum.
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $category));
        }

        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->orderByDesc('spent_at')->orderByDesc('id');
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => ExpenseResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * Rekap satu periode: total per kategori dan totalnya — bentuk yang sama
     * dengan blok "Rincian Pengeluaran" pada laporan bulanan klinik.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $from = $request->query('from');
        $to = $request->query('to');

        // Pengeluaran tanpa kategori tetap dihitung sebagai satu baris,
        // supaya jumlah per kategori selalu sama dengan totalnya.
        $rows = Expense::query()
            ->between(is_string($from) ? $from : null, is_string($to) ? $to : null)
            ->leftJoin('categorizables', function ($join): void {
                $join->on('categorizables.categorizable_id', '=', 'expenses.id')
                    ->where('categorizables.categorizable_type', '=', 'expense');
            })
            ->leftJoin('categories', 'categories.id', '=', 'categorizables.category_id')
            ->selectRaw('categories.id as category_id, categories.name as category_name, SUM(expenses.amount) as total, COUNT(*) as entries')
            ->groupBy('categories.id', 'categories.name')
            ->get();

        return response()->json([
            'data' => [
                'by_category' => $rows->map(fn ($row) => [
                    'category' => $row->category_id !== null ? (string) $row->category_id : null,
                    'category_label' => $row->category_name ?? __('category.none'),
                    'total' => (float) $row->total,
                    'entries' => (int) $row->entries,
                ])->sortByDesc('total')->values(),
                'total' => (float) $rows->sum('total'),
                'entries' => (int) $rows->sum('entries'),
            ],
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function store(ExpenseRequest $request, ExpenseService $expenses): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $expense = $expenses->create($request->validated());

        return response()->json([
            'data' => new ExpenseResource($expense->load('recorder')),
            'meta' => ['message' => __('expense.created')],
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        return response()->json([
            'data' => new ExpenseResource($expense->load('recorder')),
            'meta' => [],
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense, ExpenseService $expenses): JsonResponse
    {
        $this->authorize('update', $expense);

        $expenses->update($expense, $request->validated());

        return response()->json([
            'data' => new ExpenseResource($expense->load('recorder')),
            'meta' => ['message' => __('expense.updated')],
        ]);
    }

    public function destroy(Expense $expense, ExpenseService $expenses): JsonResponse
    {
        $this->authorize('delete', $expense);

        $expenses->delete($expense);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('expense.deleted')],
        ]);
    }
}
