<?php

namespace Tests\Feature\Staff;

use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Ubah dan hapus staf. Yang dijaga di sini bukan sekadar "tombolnya jalan",
 * melainkan bahwa penghapusan tidak pernah menyeret riwayat klinik ikut
 * hilang — sebagian foreign key ke tabel users memakai cascade.
 */
class StaffEditDeleteTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function staff(string $name, ClinicRole $role = ClinicRole::Therapist): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => str($name)->slug().'@klinik.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => $role,
        ]);

        $user->syncRoles([$role->value]);

        return $user;
    }

    public function test_updates_name_email_and_role_at_once(): void
    {
        $this->actingAsClinicUser();
        $staff = $this->staff('Terapis Lama');

        $this->putJson($this->tenantUrl("staff/{$staff->id}"), [
            'name' => 'Terapis Baru',
            'email' => 'terapis.baru@klinik.test',
            'clinic_role' => ClinicRole::Cashier->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Terapis Baru')
            ->assertJsonPath('data.clinic_role', ClinicRole::Cashier->value);

        $staff->refresh();

        $this->assertSame('terapis.baru@klinik.test', $staff->email);
        $this->assertSame(ClinicRole::Cashier, $staff->clinic_role);
        // Peran spatie ikut berpindah, bukan hanya kolom enum-nya.
        $this->assertTrue($staff->hasRole(ClinicRole::Cashier->value));
        $this->assertFalse($staff->hasRole(ClinicRole::Therapist->value));
    }

    public function test_keeping_own_email_is_not_a_duplicate(): void
    {
        $this->actingAsClinicUser();
        $staff = $this->staff('Terapis Tetap');

        $this->putJson($this->tenantUrl("staff/{$staff->id}"), [
            'name' => 'Terapis Ganti Nama',
            'email' => $staff->email,
            'clinic_role' => $staff->clinic_role->value,
        ])->assertOk();
    }

    public function test_rejects_email_already_used_by_someone_else(): void
    {
        $this->actingAsClinicUser();
        $other = $this->staff('Terapis Lain');
        $staff = $this->staff('Terapis Ini');

        $this->putJson($this->tenantUrl("staff/{$staff->id}"), [
            'name' => 'Terapis Ini',
            'email' => $other->email,
            'clinic_role' => $staff->clinic_role->value,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_deletes_staff_who_never_recorded_anything(): void
    {
        $this->actingAsClinicUser();
        $staff = $this->staff('Salah Input');

        $this->deleteJson($this->tenantUrl("staff/{$staff->id}"))->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_refuses_to_delete_staff_who_has_recorded_transactions(): void
    {
        $this->actingAsClinicUser();
        $staff = $this->staff('Terapis Bekerja');
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'therapist_id' => $staff->id,
            'invoice_number' => 'INV-STAF-1',
            'subtotal' => 100_000,
            'paid_amount' => 100_000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => now(),
        ]);

        $this->deleteJson($this->tenantUrl("staff/{$staff->id}"))->assertStatus(422);

        // Akunnya utuh, dan transaksinya jelas tidak ikut lenyap.
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('transactions', ['invoice_number' => 'INV-STAF-1']);
    }

    public function test_listing_marks_which_staff_may_be_deleted(): void
    {
        $this->actingAsClinicUser();
        $fresh = $this->staff('Belum Bekerja');
        $working = $this->staff('Sudah Bekerja');
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => $working->id,
            'invoice_number' => 'INV-STAF-2',
            'subtotal' => 50_000,
            'paid_amount' => 50_000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => now(),
        ]);

        $rows = collect(
            $this->getJson($this->tenantUrl('staff?per_page=50'))->assertOk()->json('data')
        )->keyBy('name');

        $this->assertTrue($rows['Belum Bekerja']['can_delete']);
        $this->assertFalse($rows['Sudah Bekerja']['can_delete']);
    }

    public function test_last_admin_cannot_be_deleted(): void
    {
        $admin = $this->actingAsClinicUser(ClinicRole::Admin);

        $this->deleteJson($this->tenantUrl("staff/{$admin->id}"))->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_staff_cannot_edit_or_delete(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $target = $this->staff('Target');

        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->putJson($this->tenantUrl("staff/{$target->id}"), [
            'name' => 'Diubah Diam-diam',
            'email' => $target->email,
            'clinic_role' => ClinicRole::Admin->value,
        ])->assertForbidden();

        $this->deleteJson($this->tenantUrl("staff/{$target->id}"))->assertForbidden();

        $this->assertSame('Target', $target->fresh()->name);
    }
}
