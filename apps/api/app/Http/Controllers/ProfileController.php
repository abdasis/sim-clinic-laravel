<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAppearanceRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profil pengguna yang sedang login. Tidak ada otorisasi tambahan: yang bisa
 * dibaca dan diubah hanya dirinya sendiri.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new UserResource($request->user()), 'meta' => []]);
    }

    public function update(UpdateAppearanceRequest $request, ProfileService $service): JsonResponse
    {
        $user = $service->updateAppearance($request->user(), $request->appearance());

        return response()->json([
            'data' => new UserResource($user),
            'meta' => ['message' => __('preferences.saved')],
        ]);
    }
}
