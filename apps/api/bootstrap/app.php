<?php

use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetPermissionTeamId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant wajib sudah ter-resolve sebelum route model binding berjalan;
        // tanpa ini TenantScope belum aktif saat model dicari, sehingga
        // data tenant lain bisa terambil lewat ID di URL.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveTenant::class,
        );

        $middleware->alias([
            'resolve.tenant' => ResolveTenant::class,
            'ensure.tenant.active' => EnsureTenantActive::class,
            'permission.team' => SetPermissionTeamId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Laravel menjawab penolakan izin dengan "This action is unauthorized."
        // — bahasa Inggris dan tidak menjelaskan apa pun ke pengguna klinik.
        // Diganti pesan yang bisa dibaca, tanpa mengubah status 403-nya.
        //
        // Tipe yang ditangkap AccessDeniedHttpException, bukan
        // AuthorizationException: Laravel sudah membungkusnya lewat
        // prepareException() sebelum callback ini dijalankan.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $message = $e->getMessage();
            $isDefault = $message === '' || $message === 'This action is unauthorized.';

            return response()->json([
                'message' => $isDefault ? __('clinic.forbidden') : $message,
            ], 403);
        });
    })->create();
