<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthService $service): JsonResponse
    {
        $tenant = app('tenant');

        $result = $service->login(
            $tenant,
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
        );

        return response()->json([
            'data' => [
                'user' => UserResource::withPermissions($result['user']),
                'token' => $result['token'],
            ],
            'meta' => [
                'redirect_to' => '/'.$tenant->slug,
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
