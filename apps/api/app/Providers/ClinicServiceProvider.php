<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ClinicPermission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ClinicServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('clinic.access', function (User $user, string $module, string $action = 'r'): bool {
            return app(ClinicPermission::class)->canAccess($user->clinic_role, $module, $action);
        });
    }
}
