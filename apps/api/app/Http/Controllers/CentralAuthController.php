<?php

namespace App\Http\Controllers;

use App\Actions\LogAuditAction;
use App\Enums\UserStatus;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CentralAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            abort(422, __('auth.invalid_credentials'));
        }
        if ($user->status === UserStatus::Inactive) {
            abort(403, __('auth.unauthorized'));
        }

        $tenant = $user->tenant;

        $token = $user->createToken('spa')->plainTextToken;

        app(LogAuditAction::class)->handle('user.login', $user, $user, [
            'ip_address' => $request->ip(),
        ], $tenant);

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
