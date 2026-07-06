<?php

namespace App\Http\Controllers;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', InvitationStatus::Pending)
            ->first();

        if (! $invitation || $invitation->isExpired()) {
            abort(404, __('tenant.invitation_invalid'));
        }

        return response()->json([
            'data' => [
                'email' => $invitation->email,
                'tenant_slug' => $invitation->tenant->slug,
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

        $slug = $user->tenant->slug;

        return response()->json([
            'data' => [],
            'meta' => [
                'redirect_to' => '/'.$slug.'/login',
                'message' => __('auth.password_set'),
            ],
        ]);
    }
}
