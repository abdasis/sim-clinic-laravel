<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak akses jika tenant aktif berstatus inactive (spec 001, FR-009) → 423.
 */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if ($tenant !== null && $tenant->status === TenantStatus::Inactive) {
            abort(423, __('tenant.inactive'));
        }

        return $next($request);
    }
}
