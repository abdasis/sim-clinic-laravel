<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;

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

    public function accept(AcceptInvitationRequest $request, string $token, InvitationService $service): JsonResponse
    {
        $user = $service->accept($token, $request->validated('password'));

        return response()->json([
            'data' => [],
            'meta' => [
                'redirect_to' => '/'.$user->tenant->slug.'/login',
                'message' => __('auth.password_set'),
            ],
        ]);
    }
}
