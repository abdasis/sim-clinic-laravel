<?php

namespace App\Http\Controllers;

use App\Http\Requests\CentralLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class CentralAuthController extends Controller
{
    public function login(CentralLoginRequest $request, AuthService $service): JsonResponse
    {
        $result = $service->loginPlatform(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
        );

        $user = $result['user'];
        $token = $result['token'];
        $tenant = $user->tenant;

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'tenant' => ['slug' => $tenant?->slug],
                'token' => $token,
            ],
            'meta' => [
                'redirect_to' => '/'.$tenant?->slug,
            ],
        ]);
    }
}
