<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Transaction\PayTransactionAction;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Poin hanya untuk member — satu-satunya beda member dari pelanggan biasa.
 *
 * Yang dijaga di sini dua arah sekaligus: pelanggan biasa tidak boleh diam-diam
 * mengumpulkan poin, dan poin yang sudah terlanjur jadi hak pasien tidak boleh
 * hangus hanya karena keanggotaannya berubah.
 */
class MemberOnlyPointsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function patient(bool $member, int $points = 0): Patient
    {
        return Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_member' => $member,
            'loyalty_points' => $points,
        ]);
    }

    private function payFully(Patient $patient, float $subtotal = 200_000): Transaction
    {
        $transaction = Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'subtotal' => $subtotal,
        ]);

        app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash',
            'amount' => $subtotal,
            'paid_at' => now(),
        ]);

        return $transaction->fresh();
    }

    public function test_a_member_collects_points(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->patient(member: true);

        $transaction = $this->payFully($patient);

        $this->assertSame(20, $transaction->points_earned);
        $this->assertSame(20, $patient->fresh()->loyalty_points);
    }

    /** Pelanggan biasa membayar penuh dan tidak mendapat apa pun. */
    public function test_a_non_member_collects_nothing(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->patient(member: false);

        $transaction = $this->payFully($patient);

        $this->assertSame(0, $transaction->points_earned);
        $this->assertSame(0, $patient->fresh()->loyalty_points);
    }

    /**
     * Saldo yang sudah terkumpul tetap milik pasien.
     *
     * Keanggotaan yang dicabut menghentikan pertumbuhannya, bukan menyita apa
     * yang sudah diberikan — pasien sudah membelanjakan uangnya untuk itu.
     */
    public function test_revoking_membership_keeps_the_balance_already_earned(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->patient(member: true, points: 40);

        $patient->update(['is_member' => false]);

        $this->assertSame(40, $patient->fresh()->loyalty_points);

        // Tapi transaksi berikutnya tidak menambah lagi.
        $this->payFully($patient);

        $this->assertSame(40, $patient->fresh()->loyalty_points);
    }

    /** Poin yang sudah jadi hak pasien tetap bisa ditukar di kasir. */
    public function test_a_former_member_can_still_redeem_what_was_earned(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->patient(member: false, points: 50);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 200000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['service_id' => $service->id, 'qty' => 1]],
            'points_redeemed' => 30,
        ])->assertCreated();

        $this->assertSame(20, $patient->fresh()->loyalty_points);
    }

    /** Mendaftarkan member mencatat tanggalnya, tanpa perlu dikirim dari layar. */
    public function test_registering_a_member_stamps_the_date(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->patient(member: false);

        $this->assertNull($patient->member_since);

        $patient->update(['is_member' => true]);

        $this->assertNotNull($patient->fresh()->member_since);
        $this->assertSame(
            now()->toDateString(),
            $patient->fresh()->member_since->toDateString(),
        );
    }

    /** Mencabut keanggotaan membersihkan tanggalnya, bukan meninggalkan sisa. */
    public function test_revoking_membership_clears_the_date(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->patient(member: true);
        $patient->update(['is_member' => true]);

        $patient->update(['is_member' => false]);

        $this->assertNull($patient->fresh()->member_since);
    }

    /**
     * Menyimpan ulang data pasien tidak boleh memundurkan sejak kapan dia
     * jadi member — tanggalnya milik pendaftaran, bukan milik penyuntingan
     * terakhir.
     */
    public function test_editing_a_member_does_not_move_the_join_date(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->patient(member: false);
        $patient->update(['is_member' => true]);

        $joined = $patient->fresh()->member_since->toDateString();

        $this->travel(10)->days();
        $patient->fresh()->update(['name' => 'Nama Baru']);

        $this->assertSame($joined, $patient->fresh()->member_since->toDateString());
    }

    /** Status keanggotaan ikut terbaca di daftar pasien. */
    public function test_membership_is_reported_on_the_patient_list(): void
    {
        $this->actingAsClinicUser();
        $this->patient(member: true, points: 15);

        $this->getJson($this->tenantUrl('patients'))
            ->assertOk()
            ->assertJsonPath('data.0.is_member', true)
            ->assertJsonPath('data.0.loyalty_points', 15);
    }

    /** Pendaftaran member bisa lewat formulir pasien seperti data lainnya. */
    public function test_a_patient_can_be_registered_as_a_member_from_the_form(): void
    {
        $this->actingAsClinicUser();
        $patient = $this->patient(member: false);

        $this->putJson($this->tenantUrl("patients/{$patient->id}"), [
            'name' => $patient->name,
            'whatsapp' => $patient->whatsapp,
            'is_member' => true,
        ])->assertOk()->assertJsonPath('data.is_member', true);

        $this->assertTrue($patient->fresh()->is_member);
    }
}
