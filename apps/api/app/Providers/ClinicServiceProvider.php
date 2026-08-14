<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\StaffPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ClinicServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // User adalah model staf klinik; auto-discovery mencari UserPolicy
        // yang tidak ada, jadi mapping-nya dipasang eksplisit.
        Gate::policy(User::class, StaffPolicy::class);
    }
}
