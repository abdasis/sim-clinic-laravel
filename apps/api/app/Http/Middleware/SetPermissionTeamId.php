<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set team context spatie (team = tenant) agar role/permission terisolasi
 * per tenant. Request tanpa tenant terikat (route central) memakai team
 * platform.
 */
class SetPermissionTeamId
{
    /**
     * Sentinel team untuk role lintas tenant (platform_admin, tenant_admin,
     * member). Tidak memakai null karena pivot model_has_roles.tenant_id
     * bagian dari primary key sehingga wajib NOT NULL, dan tidak ada tenant
     * ber-id 0.
     */
    public const PLATFORM_TEAM_ID = 0;

    public function handle(Request $request, Closure $next): Response
    {
        $teamId = app()->bound('tenant') ? app('tenant')->id : self::PLATFORM_TEAM_ID;

        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);

        return $next($request);
    }
}
