<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\MembershipTierRequest;
use App\Http\Resources\MembershipTierResource;
use App\Models\MembershipTier;
use App\Services\MembershipService;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipTierController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MembershipTier::class);

        $params = $this->dataTableParams($request);

        $query = MembershipTier::query()->withCount('patients');

        if ($params['search']) {
            Search::apply($query, ['name', 'description'], $params['search']);
        }

        $status = $params['filters']['status'] ?? 'all';

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Nama diurutkan tanpa peduli kapital, sama seperti katalog lain.
        $this->applyCatalogSort($query, $params, ['discount_value', 'status', 'created_at']);

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => MembershipTierResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(MembershipTierRequest $request, MembershipService $memberships): JsonResponse
    {
        $this->authorize('create', MembershipTier::class);

        $tier = $memberships->create($request->validated());

        return response()->json([
            'data' => new MembershipTierResource($tier),
            'meta' => ['message' => __('membership.created')],
        ], 201);
    }

    public function update(
        MembershipTierRequest $request,
        MembershipTier $membershipTier,
        MembershipService $memberships,
    ): JsonResponse {
        $this->authorize('update', $membershipTier);

        $tier = $memberships->update($membershipTier, $request->validated());

        return response()->json([
            'data' => new MembershipTierResource($tier),
            'meta' => ['message' => __('membership.updated')],
        ]);
    }

    public function destroy(MembershipTier $membershipTier, MembershipService $memberships): JsonResponse
    {
        $this->authorize('delete', $membershipTier);

        $memberships->delete($membershipTier);

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('membership.deleted')],
        ]);
    }
}
