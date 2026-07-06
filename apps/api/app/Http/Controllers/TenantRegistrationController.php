<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterTenantRequest;
use App\Services\TenantRegistrationService;
use Illuminate\Http\JsonResponse;

class TenantRegistrationController extends Controller
{
    public function store(RegisterTenantRequest $request, TenantRegistrationService $service): JsonResponse
    {
        $result = $service->register($request->validated());
        $tenant = $result['tenant'];
        $user = $result['user'];

        return response()->json([
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'meta' => [
                'redirect_to' => '/'.$tenant->slug.'/login',
                'message' => __('tenant.registered'),
            ],
        ], 201);
    }
}
