<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve slug {tenant} dari route → set tenant aktif di container (spec 001, FR-007).
 * Slug tidak dikenal → 404 dengan pesan i18n.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            abort(404, __('tenant.not_found'));
        }

        app()->instance('tenant', $tenant);

        // Argumen controller diteruskan Laravel berdasarkan posisi, bukan nama.
        // Tanpa ini {tenant} ikut terkirim sebagai argumen pertama sehingga
        // setiap method yang menerima model dari route menolak dengan TypeError.
        $request->route()->forgetParameter('tenant');

        return $next($request);
    }
}
