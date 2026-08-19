<?php

namespace Tests\Feature\ActivityLog;

use App\Actions\LogAuditAction;
use App\Enums\ClinicRole;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Log aktivitas: yang dijaga di sini adalah batas tenant, batas peran, dan
 * bentuk diff yang dibaca halaman rinciannya.
 */
class ActivityLogApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_admin_reads_activity_log_of_own_tenant_only(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        app(LogAuditAction::class)->handle(
            'patient.created',
            null,
            null,
            ['new' => ['name' => 'Sinta']],
            'Menambahkan pasien Sinta.',
        );

        // Milik klinik lain: tenant_id ditulis eksplisit, bukan dari container.
        app(LogAuditAction::class)->handle(
            'patient.created',
            null,
            null,
            ['tenant_id' => $this->tenant->id + 99, 'new' => ['name' => 'Bukan Punya Kita']],
            'Menambahkan pasien di klinik lain.',
        );

        $response = $this->getJson($this->tenantUrl('activity-logs'))->assertOk();

        $descriptions = collect($response->json('data'))->pluck('description');

        $this->assertTrue($descriptions->contains('Menambahkan pasien Sinta.'));
        $this->assertFalse($descriptions->contains('Menambahkan pasien di klinik lain.'));

        // Modulnya disaring supaya jejak penyiapan klinik (peran dan aturan
        // fee bawaan) tidak ikut terhitung di angka ini.
        $filtered = $this->getJson($this->tenantUrl('activity-logs').'?filter[module]=patient')->assertOk();

        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('meta.total', 1);
        $filtered->assertJsonPath('data.0.module_label', 'Pasien');
        $filtered->assertJsonPath('data.0.event_label', 'Dibuat');
    }

    public function test_cashier_without_permission_is_rejected(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('activity-logs'))->assertForbidden();
    }

    public function test_creating_a_patient_leaves_a_readable_trail(): void
    {
        $user = $this->actingAsClinicUser(ClinicRole::Admin);

        $this->postJson($this->tenantUrl('patients'), [
            'name' => 'Rani Kusuma',
            'whatsapp' => '081234567890',
            'gender' => 'female',
            'birth_date' => '1995-04-02',
        ])->assertCreated();

        $response = $this->getJson($this->tenantUrl('activity-logs').'?filter[module]=patient')->assertOk();

        $response->assertJsonPath('data.0.event', 'patient.created');
        $response->assertJsonPath('data.0.causer_name', $user->name);
        $response->assertJsonPath('data.0.subject_name', 'Rani Kusuma');
        // subject_type memakai alias morph map, bukan nama kelas.
        $this->assertSame('patient', $response->json('data.0.subject_type'));
        $response->assertJsonPath('data.0.subject_type_label', 'Pasien');
    }

    public function test_detail_exposes_old_and_new_values_side_by_side(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        app(LogAuditAction::class)->handle(
            'service.updated',
            null,
            null,
            ['old' => ['price' => 100000], 'new' => ['price' => 125000]],
            'Memperbarui harga layanan Facial.',
        );

        $id = Activity::query()->latest('id')->value('id');

        $response = $this->getJson($this->tenantUrl("activity-logs/{$id}"))->assertOk();

        $response->assertJsonPath('data.changes.0.field', 'price');
        $response->assertJsonPath('data.changes.0.old', 100000);
        $response->assertJsonPath('data.changes.0.new', 125000);
        $response->assertJsonPath('data.has_details', true);
    }

    public function test_detail_of_another_tenant_activity_is_not_found(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        app(LogAuditAction::class)->handle(
            'service.updated',
            null,
            null,
            ['tenant_id' => $this->tenant->id + 99, 'new' => ['price' => 1]],
            'Perubahan milik klinik lain.',
        );

        $id = Activity::query()->latest('id')->value('id');

        $this->getJson($this->tenantUrl("activity-logs/{$id}"))->assertNotFound();
    }

    public function test_filter_options_only_list_events_that_actually_happened(): void
    {
        $user = $this->actingAsClinicUser(ClinicRole::Admin);

        app(LogAuditAction::class)->handle('booking.created', null, $user, [], 'Membuat booking.');

        $response = $this->getJson($this->tenantUrl('activity-logs/filters'))->assertOk();

        $modules = collect($response->json('data.modules'))->pluck('value');
        $events = collect($response->json('data.events'))->pluck('value');

        $this->assertTrue($modules->contains('booking'));
        $this->assertTrue($events->contains('booking.created'));
        $this->assertFalse($events->contains('transaction.paid'));
        $this->assertSame($user->name, $response->json('data.causers.0.label'));
    }
}
