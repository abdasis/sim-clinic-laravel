<?php

namespace App\Providers;

use App\Models\CompanyProfileSetting;
use App\Models\User;
use App\Policies\CompanyProfileContentPolicy;
use App\Policies\RolePolicy;
use App\Policies\StaffPolicy;
use App\Support\CompanyContentRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class ClinicServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // User adalah model staf klinik; auto-discovery mencari UserPolicy
        // yang tidak ada, jadi mapping-nya dipasang eksplisit.
        Gate::policy(User::class, StaffPolicy::class);

        // Sembilan entitas konten memakai satu policy yang sama; kalau
        // dibiarkan, auto-discovery mencari sembilan policy yang tidak ada.
        foreach (CompanyContentRegistry::models() as $model) {
            Gate::policy($model, CompanyProfileContentPolicy::class);
        }

        Gate::policy(CompanyProfileSetting::class, CompanyProfileContentPolicy::class);

        // Role datang dari paket spatie, jadi auto-discovery tidak akan
        // menemukan policy-nya di app/Policies.
        Gate::policy(Role::class, RolePolicy::class);
    }
}
