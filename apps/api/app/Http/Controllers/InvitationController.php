<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function show(string $token, InvitationService $service): JsonResponse
    {
        $invitation = $service->resolvePending($token);

        return response()->json([
            'data' => [
                'email' => $invitation->email,
                'tenant_slug' => $invitation->tenant->slug,
                'role' => $invitation->role->value,
                'clinic_role' => $invitation->clinic_role?->value,
            ],
            'meta' => [],
        ]);
    }

    public function accept(Request $request, string $token, InvitationService $service): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/'],
        ], [
            'password.regex' => __('validation.password_complexity'),
        ]);

        $user = $service->accept($token, $validated['password']);

        return response()->json([
            'data' => [],
            'meta' => [
                'redirect_to' => '/'.$user->tenant->slug.'/login',
                'message' => __('auth.password_set'),
            ],
        ]);
    }
}
