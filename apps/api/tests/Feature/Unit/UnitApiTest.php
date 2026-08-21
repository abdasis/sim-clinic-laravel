<?php

namespace Tests\Feature\Unit;

use App\Enums\ClinicRole;
use App\Enums\ServiceStatus;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * CRUD satuan produk lewat HTTP.
 *
 * Seluruh modul ini sebelumnya tidak tersentuh test sama sekali. Yang dijaga
 * di sini: namanya unik per klinik, arsip adalah jalur utama penghapusan,
 * hapus permanen ditolak selama masih ada produk yang memakainya, dan satuan
 * klinik lain tidak pernah ikut terbaca.
 */
class UnitApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function make(string $name, ServiceStatus $status = ServiceStatus::Active): Unit
    {
        return Unit::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'status' => $status,
        ]);
    }

    public function test_admin_creates_a_unit(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('units'), ['name' => 'Botol'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Botol')
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(1, Unit::query()->count());
    }

    /**
     * Nama ganda ditolak sebagai galat formulir, bukan galat SQL — kolomnya
     * memang unik per klinik.
     */
    public function test_duplicate_name_is_rejected_as_a_form_error(): void
    {
        $this->actingAsClinicUser();
        $this->make('Botol');

        $this->postJson($this->tenantUrl('units'), ['name' => 'Botol'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Klinik lain boleh memakai nama yang sama; keunikannya per tenant, bukan
     * global.
     */
    public function test_another_clinic_may_reuse_the_same_name(): void
    {
        $this->actingAsClinicUser();
        $this->make('Botol');

        $other = $this->createTenant('klinik-lain');
        $this->actingAsClinicUser(ClinicRole::Admin, $other);

        $this->postJson($this->tenantUrl('units', $other), ['name' => 'Botol'])
            ->assertCreated();
    }

    public function test_listing_returns_units_with_usage_count(): void
    {
        $this->actingAsClinicUser();
        $unit = $this->make('Botol');
        Product::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'unit_id' => $unit->id,
        ]);

        $this->getJson($this->tenantUrl('units'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Botol')
            ->assertJsonPath('data.0.usage_count', 2);
    }

    /**
     * Formulir produk hanya boleh menawarkan satuan aktif; halaman kelola
     * meminta arsipnya secara eksplisit lewat filter.
     */
    public function test_listing_hides_archived_units_unless_asked(): void
    {
        $this->actingAsClinicUser();
        $this->make('Botol');
        $this->make('Vial', ServiceStatus::Archived);

        $this->getJson($this->tenantUrl('units?filter[status]=active'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Botol');

        $this->getJson($this->tenantUrl('units?filter[status]=all'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_listing_can_be_searched_by_name(): void
    {
        $this->actingAsClinicUser();
        $this->make('Botol');
        $this->make('Tube');

        $this->getJson($this->tenantUrl('units?search=Tub'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Tube');
    }

    public function test_detail_returns_one_unit(): void
    {
        $this->actingAsClinicUser();
        $unit = $this->make('Botol');

        $this->getJson($this->tenantUrl("units/{$unit->id}"))
            ->assertOk()
            ->assertJsonPath('data.id', $unit->id)
            ->assertJsonPath('data.usage_count', 0);
    }

    public function test_admin_renames_a_unit(): void
    {
        $this->actingAsClinicUser();
        $unit = $this->make('Botol');

        $this->putJson($this->tenantUrl("units/{$unit->id}"), ['name' => 'Botol Kaca'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Botol Kaca');

        $this->assertSame('Botol Kaca', $unit->fresh()->name);
    }

    /** Namanya sendiri tidak boleh dianggap ganda saat menyimpan ulang. */
    public function test_updating_a_unit_may_keep_its_own_name(): void
    {
        $this->actingAsClinicUser();
        $unit = $this->make('Botol');

        $this->putJson($this->tenantUrl("units/{$unit->id}"), [
            'name' => 'Botol',
            'status' => 'archived',
        ])->assertOk();

        $this->assertSame(ServiceStatus::Archived, $unit->fresh()->status);
    }

    /**
     * Hapus dari daftar berarti arsip: produk yang sudah memakainya tetap
     * bisa menampilkan satuannya.
     */
    public function test_delete_archives_instead_of_removing(): void
    {
        $this->actingAsClinicUser();
        $unit = $this->make('Botol');

        $this->deleteJson($this->tenantUrl("units/{$unit->id}"))->assertOk();

        $this->assertSame(ServiceStatus::Archived, $unit->fresh()->status);
        $this->assertSame(1, Unit::query()->count());
    }

    public function test_force_delete_removes_an_unused_unit(): void
    {
        $this->actingAsClinicUser();
        $unit = $this->make('Botol');

        $this->deleteJson($this->tenantUrl("units/{$unit->id}/force"))->assertOk();

        $this->assertSame(0, Unit::query()->count());
    }

    /**
     * Foreign key-nya RESTRICT; menolaknya di sini membuat penolakan itu
     * terbaca sebagai pesan, bukan galat basis data.
     */
    public function test_force_delete_is_refused_while_a_product_still_uses_it(): void
    {
        $this->actingAsClinicUser();
        $unit = $this->make('Botol');
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'unit_id' => $unit->id,
        ]);

        $this->deleteJson($this->tenantUrl("units/{$unit->id}/force"))
            ->assertStatus(422);

        $this->assertSame(1, Unit::query()->count());
    }

    /** Satuan hanya urusan admin — formulir produk pun cuma dibuka admin. */
    public function test_non_admin_roles_cannot_reach_units(): void
    {
        foreach ([ClinicRole::Doctor, ClinicRole::Therapist, ClinicRole::Cashier] as $role) {
            $this->actingAsClinicUser($role);

            $this->getJson($this->tenantUrl('units'))->assertForbidden();
            $this->postJson($this->tenantUrl('units'), ['name' => 'Botol '.$role->value])
                ->assertForbidden();
        }
    }

    /** Satuan milik klinik lain tidak pernah ikut terbaca. */
    public function test_units_are_isolated_per_clinic(): void
    {
        $this->actingAsClinicUser();
        $this->make('Botol');

        $other = $this->createTenant('klinik-lain');
        $this->actingAsClinicUser(ClinicRole::Admin, $other);

        $this->getJson($this->tenantUrl('units', $other))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
