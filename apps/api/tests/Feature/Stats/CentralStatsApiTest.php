<?php

namespace Tests\Feature\Stats;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class CentralStatsApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_platform_admin_reads_tenant_kpis_and_weekly_trend(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        $response = $this->getJson('/api/central/stats')->assertOk();

        $response->assertJsonPath('meta.module', 'central');
        $response->assertJsonPath('meta.weeks', 12);
        $response->assertJsonCount(12, 'data.trend');
        $response->assertJsonPath('data.kpis.0.key', 'total');
        $this->assertSame(1, $response->json('data.kpis.0.value'));
    }

    public function test_tenant_member_cannot_read_platform_stats(): void
    {
        $this->actingAsClinicUser();

        $this->getJson('/api/central/stats')->assertForbidden();
    }

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'name' => 'Admin Platform',
            'email' => 'platform@sim-clinic.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::PlatformAdmin,
            'status' => UserStatus::Active,
        ]);
    }
}
