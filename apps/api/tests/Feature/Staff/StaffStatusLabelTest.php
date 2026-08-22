<?php

namespace Tests\Feature\Staff;

use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Label status staf ikut dikirim, bukan hanya nilai mentahnya.
 *
 * Tabel staf berbahasa Indonesia menampilkan "active" di kolom Status —
 * satu-satunya kolom yang tidak terjemah, di antara Peran yang sudah rapi.
 * Penyebabnya bukan di layar: resource-nya memang tidak pernah mengirim
 * labelnya, jadi tidak ada yang bisa ditampilkan selain nilai enum.
 */
class StaffStatusLabelTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makeStaff(UserStatus $status, string $name): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => str($name)->slug().'@meba.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => $status,
            'clinic_role' => ClinicRole::Therapist,
        ]);
    }

    public function test_every_staff_row_carries_a_readable_status(): void
    {
        $this->actingAsClinicUser();
        $this->makeStaff(UserStatus::Active, 'Jasmin');
        $this->makeStaff(UserStatus::Inactive, 'Mutia');

        $rows = collect(
            $this->getJson($this->tenantUrl('staff'))->assertOk()->json('data'),
        )->keyBy('name');

        $this->assertSame('Aktif', $rows['Jasmin']['status_label']);
        $this->assertSame('Nonaktif', $rows['Mutia']['status_label']);
    }

    /** Nilai mentahnya tetap dikirim: layar memakainya untuk memilih warna. */
    public function test_the_raw_status_is_still_sent_alongside_the_label(): void
    {
        $this->actingAsClinicUser();
        $this->makeStaff(UserStatus::Inactive, 'Mutia');

        $row = collect(
            $this->getJson($this->tenantUrl('staff'))->assertOk()->json('data'),
        )->firstWhere('name', 'Mutia');

        $this->assertSame('inactive', $row['status']);
        $this->assertNotSame($row['status'], $row['status_label']);
    }
}
