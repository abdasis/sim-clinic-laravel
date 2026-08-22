<?php

namespace Tests\Feature\MedicalRecord;

use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Siapa yang boleh membaca belanja pasien, dan dari klinik mana.
 *
 * Riwayat pembelian pasien adalah data medis: ia menjawab skincare apa yang
 * sedang dipakai seseorang. Endpoint barunya perlu dijaga sekeras rekam
 * medisnya sendiri — bukan sekadar mengikuti penjagaan POS, yang terbuka
 * untuk kasir.
 */
class PatientPurchaseAccessTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makePatientWithPurchase(Tenant $tenant, string $product): Patient
    {
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Transaction::create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => 100000,
            'paid_amount' => 100000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => now(),
        ])->items()->create([
            'tenant_id' => $tenant->id,
            'product_id' => Product::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $product,
            ])->id,
            'name' => $product,
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        return $patient;
    }

    private function rebind(Tenant $tenant): void
    {
        app()->instance('tenant', $tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    }

    /**
     * Penjagaan terpenting: belanja pasien klinik lain tidak boleh terbaca,
     * bahkan lewat id pasien yang ditebak.
     */
    public function test_another_clinics_patient_is_not_reachable(): void
    {
        $this->actingAsClinicUser();

        $other = $this->createTenant('klinik-lain');
        $foreignPatient = $this->makePatientWithPurchase($other, 'Punya Tetangga');

        $this->rebind($this->tenant);

        $this->getJson($this->tenantUrl("patients/{$foreignPatient->id}/purchases"))
            ->assertNotFound();
    }

    /** Rekam medis pasien klinik lain juga tidak boleh dibuka dari sini. */
    public function test_another_clinics_records_are_not_reachable(): void
    {
        $this->actingAsClinicUser();

        $other = $this->createTenant('klinik-lain');
        $foreignPatient = $this->makePatientWithPurchase($other, 'Punya Tetangga');

        $this->rebind($this->tenant);

        $this->getJson($this->tenantUrl("patients/{$foreignPatient->id}/medical-records"))
            ->assertNotFound();
    }

    /** Dokter membacanya: itu justru alasan datanya ditampilkan. */
    public function test_a_doctor_may_read_the_purchase_history(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $patient = $this->makePatientWithPurchase($this->tenant, 'Serum Vitamin C');

        $this->getJson($this->tenantUrl("patients/{$patient->id}/purchases"))
            ->assertOk()
            ->assertJsonPath('data.0.items.0.name', 'Serum Vitamin C');
    }

    /**
     * Kasir ditolak, sama seperti rekam medis (FR-044). Ia memang menjual
     * produknya, tapi membaca riwayat pemakaian seorang pasien adalah
     * kewenangan yang berbeda dari mencatat penjualannya.
     */
    public function test_a_cashier_is_refused(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $patient = $this->makePatientWithPurchase($this->tenant, 'Serum Vitamin C');

        $this->getJson($this->tenantUrl("patients/{$patient->id}/purchases"))
            ->assertForbidden();
    }

    /** Tanpa token sama sekali tidak ada yang terbaca. */
    public function test_a_guest_is_refused(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->makePatientWithPurchase($this->tenant, 'Serum Vitamin C');

        app('auth')->forgetGuards();

        $this->getJson($this->tenantUrl("patients/{$patient->id}/purchases"))
            ->assertUnauthorized();
    }
}
