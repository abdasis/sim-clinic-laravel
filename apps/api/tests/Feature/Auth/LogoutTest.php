<?php

namespace Tests\Feature\Auth;

use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Keluar dari klinik.
 *
 * Yang dijaga di sini: tokennya benar-benar dicabut dari basis data — bukan
 * sekadar dibuang di sisi peramban — dan hanya token yang dipakai keluar yang
 * hilang, sehingga sesi perangkat lain milik orang yang sama tetap hidup.
 *
 * Penggunanya sengaja tidak dibuat lewat `actingAsClinicUser`: helper itu
 * memasang token semu Sanctum yang menelan header Authorization, sehingga
 * pencabutan token tidak akan pernah benar-benar teruji.
 */
class LogoutTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const PASSWORD = 'password123';

    private function makeUser(): User
    {
        $this->tenant ??= $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Klinik',
            'email' => 'admin@'.$this->tenant->slug.'.test',
            'password' => Hash::make(self::PASSWORD),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Admin,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $user->assignRole(ClinicRole::Admin->value);

        return $user;
    }

    private function login(User $user): string
    {
        $response = $this->postJson('/api/'.$this->tenant->slug.'/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertOk();

        return $response->json('data.token');
    }

    private function logoutUrl(): string
    {
        return '/api/'.$this->tenant->slug.'/logout';
    }

    public function test_logout_revokes_the_token_that_was_used(): void
    {
        $user = $this->makeUser();
        $token = $this->login($user);

        $this->assertSame(1, PersonalAccessToken::query()->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($this->logoutUrl())
            ->assertNoContent();

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    /** Token yang sudah dicabut tidak boleh membuka apa pun lagi. */
    public function test_a_revoked_token_no_longer_authenticates(): void
    {
        $user = $this->makeUser();
        $token = $this->login($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($this->logoutUrl())
            ->assertNoContent();

        // Guard menyimpan pengguna yang sudah diresolusi di permintaan
        // sebelumnya; tanpa dilupakan, yang teruji cuma cache dalam proses
        // test, bukan token yang sudah dicabut.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($this->tenantUrl('identity'))
            ->assertUnauthorized();
    }

    /**
     * Keluar di satu perangkat tidak boleh menendang perangkat lain milik
     * orang yang sama — hanya token yang dipakai yang dicabut.
     */
    public function test_other_sessions_of_the_same_user_survive(): void
    {
        $user = $this->makeUser();
        $ponsel = $this->login($user);
        $komputer = $this->login($user);

        $this->assertSame(2, PersonalAccessToken::query()->count());

        $this->withHeader('Authorization', 'Bearer '.$ponsel)
            ->postJson($this->logoutUrl())
            ->assertNoContent();

        $this->assertSame(1, PersonalAccessToken::query()->count());

        // Sama seperti di atas: tanpa ini yang lolos bisa jadi cache guard,
        // bukan token komputer yang memang masih sah.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$komputer)
            ->getJson($this->tenantUrl('identity'))
            ->assertOk();
    }

    public function test_logout_requires_a_token(): void
    {
        $this->tenant = $this->createTenant();

        $this->postJson($this->logoutUrl())->assertUnauthorized();
    }
}
