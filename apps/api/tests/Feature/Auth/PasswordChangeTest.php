<?php

namespace Tests\Feature\Auth;

use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Staf mengganti kata sandinya sendiri, dan admin menyetel ulang milik staf.
 *
 * Klinik tidak punya jalur lupa-kata-sandi lewat email, jadi tanpa keduanya
 * terapis yang lupa kata sandinya terkunci selamanya — dan satu-satunya
 * jalan keluar adalah membuat akun baru, yang memutus riwayat kerjanya.
 *
 * Yang paling dijaga di sini bukan tersimpannya kata sandi baru, melainkan
 * dua hal di sekitarnya: kata sandi lama tetap diminta, dan sesi lain
 * benar-benar dicabut.
 */
class PasswordChangeTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const OLD = 'password123';

    private const NEW = 'kata-sandi-baru-9';

    private function makeStaff(string $name, ClinicRole $role = ClinicRole::Therapist): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => str($name)->slug().'@meba.test',
            'password' => Hash::make(self::OLD),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => $role,
        ]);
    }

    /** Masuk sungguhan supaya token yang dipakai benar-benar ada. */
    private function loginAs(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/'.$this->tenant->slug.'/login', [
            'email' => $user->email,
            'password' => self::OLD,
        ])->assertOk()->json('data.token');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function changeOwn(string $token, array $payload)
    {
        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/'.$this->tenant->slug.'/me/password', $payload);
    }

    public function test_a_therapist_can_change_their_own_password(): void
    {
        $this->actingAsClinicUser();
        $jasmin = $this->makeStaff('Jasmin');
        $token = $this->loginAs($jasmin);

        $this->changeOwn($token, [
            'current_password' => self::OLD,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::NEW, $jasmin->fresh()->password));
    }

    /**
     * Penjagaan terpenting: sesi yang tertinggal terbuka di komputer klinik
     * tidak boleh cukup untuk mengunci pemiliknya keluar dari akunnya.
     */
    public function test_the_current_password_is_required(): void
    {
        $this->actingAsClinicUser();
        $jasmin = $this->makeStaff('Jasmin');
        $token = $this->loginAs($jasmin);

        $this->changeOwn($token, [
            'current_password' => 'tebakan-salah',
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check(self::OLD, $jasmin->fresh()->password));
    }

    /** Salah ketik pada kolom yang tidak terlihat ditangkap konfirmasinya. */
    public function test_the_confirmation_must_match(): void
    {
        $this->actingAsClinicUser();
        $token = $this->loginAs($this->makeStaff('Jasmin'));

        $this->changeOwn($token, [
            'current_password' => self::OLD,
            'password' => self::NEW,
            'password_confirmation' => 'beda-sendiri',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_a_too_short_password_is_rejected(): void
    {
        $this->actingAsClinicUser();
        $token = $this->loginAs($this->makeStaff('Jasmin'));

        $this->changeOwn($token, [
            'current_password' => self::OLD,
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    /** Mengganti dengan kata sandi yang sama tidak mengganti apa pun. */
    public function test_the_new_password_must_differ(): void
    {
        $this->actingAsClinicUser();
        $token = $this->loginAs($this->makeStaff('Jasmin'));

        $this->changeOwn($token, [
            'current_password' => self::OLD,
            'password' => self::OLD,
            'password_confirmation' => self::OLD,
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    /**
     * Kalau kata sandinya diganti justru karena ada yang mengetahuinya,
     * orang itu harus ikut terlempar keluar.
     */
    public function test_other_sessions_are_revoked(): void
    {
        $this->actingAsClinicUser();
        $jasmin = $this->makeStaff('Jasmin');

        $stolen = $this->loginAs($jasmin);
        $mine = $this->loginAs($jasmin);

        $this->changeOwn($mine, [
            'current_password' => self::OLD,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$stolen)
            ->getJson('/api/'.$this->tenant->slug.'/me')
            ->assertUnauthorized();
    }

    /** Sesi yang sedang dipakai tetap hidup; ia tidak boleh terlempar sendiri. */
    public function test_the_session_doing_the_change_survives(): void
    {
        $this->actingAsClinicUser();
        $jasmin = $this->makeStaff('Jasmin');
        $token = $this->loginAs($jasmin);

        $this->changeOwn($token, [
            'current_password' => self::OLD,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/'.$this->tenant->slug.'/me')
            ->assertOk();
    }

    /** Tercatat, tanpa pernah memuat kata sandinya. */
    public function test_the_change_is_logged_without_the_password(): void
    {
        $this->actingAsClinicUser();
        $token = $this->loginAs($this->makeStaff('Jasmin'));

        $this->changeOwn($token, [
            'current_password' => self::OLD,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertOk();

        $activity = Activity::where('event', 'user.password_changed')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringNotContainsString(self::NEW, json_encode($activity->properties));
        $this->assertStringNotContainsString(self::OLD, json_encode($activity->properties));
    }

    // ---- Admin menyetel ulang kata sandi staf ----

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reset(User $staff, array $payload)
    {
        return $this->putJson($this->tenantUrl("staff/{$staff->id}/password"), $payload);
    }

    public function test_an_admin_resets_a_therapists_password(): void
    {
        $this->actingAsClinicUser();
        $jasmin = $this->makeStaff('Jasmin');

        $this->reset($jasmin, [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::NEW, $jasmin->fresh()->password));
    }

    /** Dokter pun sama; yang membedakan wewenang admin, bukan peran sasarannya. */
    public function test_a_doctors_password_can_be_reset_too(): void
    {
        $this->actingAsClinicUser();
        $dokter = $this->makeStaff('Dokter Rani', ClinicRole::Doctor);

        $this->reset($dokter, [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::NEW, $dokter->fresh()->password));
    }

    /** Seluruh sesi staf itu dicabut, tanpa kecuali. */
    public function test_resetting_revokes_every_session_of_that_staff(): void
    {
        $admin = $this->actingAsClinicUser();
        $jasmin = $this->makeStaff('Jasmin');
        $token = $this->loginAs($jasmin);

        // Kembali sebagai admin yang sama, bukan membuat admin kedua:
        // emailnya diturunkan dari slug klinik sehingga akan bentrok.
        Sanctum::actingAs($admin);

        $this->reset($jasmin, [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/'.$this->tenant->slug.'/me')
            ->assertUnauthorized();
    }

    /** Terapis tidak boleh menyetel ulang kata sandi rekannya. */
    public function test_a_therapist_cannot_reset_someone_elses_password(): void
    {
        $this->actingAsClinicUser(ClinicRole::Therapist);
        $mutia = $this->makeStaff('Mutia');

        $this->reset($mutia, [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertForbidden();

        $this->assertTrue(Hash::check(self::OLD, $mutia->fresh()->password));
    }

    /**
     * Jalur ini melewati pemeriksaan kata sandi lama, jadi ia tidak boleh
     * jadi pintu belakang untuk mengganti kata sandi sendiri tanpa
     * mengetahuinya — mis. lewat sesi yang tertinggal terbuka.
     */
    public function test_an_admin_cannot_reset_their_own_password_this_way(): void
    {
        $admin = $this->actingAsClinicUser();

        $this->reset($admin, [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertStatus(422);

        $this->assertFalse(Hash::check(self::NEW, $admin->fresh()->password));
    }

    /** Staf klinik lain tidak bisa disentuh dari sini. */
    public function test_a_staff_from_another_clinic_is_out_of_reach(): void
    {
        $this->actingAsClinicUser();

        $other = $this->createTenant('klinik-lain');
        $foreign = User::factory()->create([
            'tenant_id' => $other->id,
            'email' => 'asing@meba.test',
            'password' => Hash::make(self::OLD),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);

        app()->instance('tenant', $this->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $this->reset($foreign, [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertNotFound();

        $this->assertTrue(Hash::check(self::OLD, $foreign->fresh()->password));
    }
}
