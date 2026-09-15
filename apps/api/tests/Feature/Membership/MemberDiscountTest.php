<?php

namespace Tests\Feature\Membership;

use App\Models\MembershipTier;
use App\Models\Patient;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Potongan member di kasir.
 *
 * Keanggotaan dibeli pasien di muka, jadi potongannya bukan kelonggaran yang
 * boleh lupa diberikan: begitu pasiennya dipilih, angkanya berkurang sendiri
 * tanpa kasir perlu mengingat siapa member dan berapa haknya.
 */
class MemberDiscountTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();

        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 200000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    private function tier(array $overrides = []): MembershipTier
    {
        return MembershipTier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gold',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'status' => 'active',
            ...$overrides,
        ]);
    }

    private function patient(array $overrides = []): Patient
    {
        return Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            ...$overrides,
        ]);
    }

    private function promoOnService(): void
    {
        $promo = Promo::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Promo September',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ]);

        $promo->items()->create([
            'tenant_id' => $this->tenant->id,
            'promotable_type' => 'service',
            'promotable_id' => $this->service->id,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function checkout(Patient $patient, array $payload = []): Transaction
    {
        $id = $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['service_id' => $this->service->id, 'qty' => 1]],
            ...$payload,
        ])->assertCreated()->json('data.id');

        return Transaction::findOrFail($id);
    }

    public function test_a_member_pays_less_without_the_cashier_doing_anything(): void
    {
        $patient = $this->patient(['membership_tier_id' => $this->tier()->id]);

        $transaction = $this->checkout($patient);

        $this->assertEqualsWithDelta(200000.0, (float) $transaction->items_total, 0.01);
        $this->assertEqualsWithDelta(20000.0, (float) $transaction->member_discount_amount, 0.01);
        $this->assertEqualsWithDelta(180000.0, (float) $transaction->subtotal, 0.01);
        $this->assertSame('Gold', $transaction->member_tier_name);
    }

    public function test_a_patient_without_a_membership_pays_the_full_price(): void
    {
        $transaction = $this->checkout($this->patient());

        $this->assertEqualsWithDelta(0.0, (float) $transaction->member_discount_amount, 0.01);
        $this->assertEqualsWithDelta(200000.0, (float) $transaction->subtotal, 0.01);
        $this->assertNull($transaction->member_tier_name);
    }

    /**
     * Bawaannya potongan member tidak menumpuk di atas promo.
     *
     * Di kebanyakan klinik keduanya memang tidak digabung, dan menumpuk
     * diam-diam berarti margin yang hilang tanpa ada yang pernah memutuskan.
     */
    public function test_a_promo_item_does_not_also_get_the_member_discount(): void
    {
        $this->promoOnService();
        $patient = $this->patient(['membership_tier_id' => $this->tier()->id]);

        $transaction = $this->checkout($patient);

        // Harga promo 160.000, tanpa potongan member lagi di atasnya.
        $this->assertEqualsWithDelta(0.0, (float) $transaction->member_discount_amount, 0.01);
        $this->assertEqualsWithDelta(160000.0, (float) $transaction->subtotal, 0.01);
        // Nama tingkatnya tetap disebut: kartunya dikenali, cuma tidak menambah.
        $this->assertSame('Gold', $transaction->member_tier_name);
    }

    /** Klinik yang memang ingin menumpuk tinggal menyalakannya. */
    public function test_a_tier_may_be_allowed_to_stack_on_promo_items(): void
    {
        $this->promoOnService();
        $patient = $this->patient([
            'membership_tier_id' => $this->tier(['stacks_with_promo' => true])->id,
        ]);

        $transaction = $this->checkout($patient);

        // 160.000 setelah promo, lalu 10% potongan member.
        $this->assertEqualsWithDelta(16000.0, (float) $transaction->member_discount_amount, 0.01);
        $this->assertEqualsWithDelta(144000.0, (float) $transaction->subtotal, 0.01);
    }

    public function test_an_expired_membership_gives_nothing(): void
    {
        $patient = $this->patient([
            'membership_tier_id' => $this->tier()->id,
            'member_until' => now()->subDay(),
        ]);

        $this->assertEqualsWithDelta(0.0, (float) $this->checkout($patient)->member_discount_amount, 0.01);
    }

    /** Hari terakhirnya masih ikut, bukan berhenti sehari lebih awal. */
    public function test_the_last_day_of_a_membership_still_counts(): void
    {
        $patient = $this->patient([
            'membership_tier_id' => $this->tier()->id,
            'member_until' => now(),
        ]);

        $this->assertEqualsWithDelta(20000.0, (float) $this->checkout($patient)->member_discount_amount, 0.01);
    }

    public function test_a_membership_that_has_not_started_gives_nothing(): void
    {
        $patient = $this->patient([
            'membership_tier_id' => $this->tier()->id,
            'member_since' => now()->addWeek(),
        ]);

        $this->assertEqualsWithDelta(0.0, (float) $this->checkout($patient)->member_discount_amount, 0.01);
    }

    /** Tingkat yang dinonaktifkan berhenti memberi potongan. */
    public function test_an_inactive_tier_gives_nothing(): void
    {
        $patient = $this->patient([
            'membership_tier_id' => $this->tier(['status' => 'inactive'])->id,
        ]);

        $this->assertEqualsWithDelta(0.0, (float) $this->checkout($patient)->member_discount_amount, 0.01);
    }

    /**
     * Kelonggaran kasir menumpuk di atas potongan member, bukan menggantinya.
     *
     * Urutannya menentukan: kalau potongan manual dihitung dari harga penuh,
     * kasir yang memberi kelonggaran justru menghapus manfaat yang sudah
     * dibayar pasien saat mendaftar jadi member.
     */
    public function test_a_manual_discount_stacks_on_top_of_the_member_discount(): void
    {
        $patient = $this->patient(['membership_tier_id' => $this->tier()->id]);

        $transaction = $this->checkout($patient, [
            'discount_type' => 'fixed',
            'discount_value' => 30000,
        ]);

        // 200.000 − 20.000 member = 180.000, lalu −30.000 dari kasir.
        $this->assertEqualsWithDelta(20000.0, (float) $transaction->member_discount_amount, 0.01);
        $this->assertEqualsWithDelta(30000.0, (float) $transaction->discount_amount, 0.01);
        $this->assertEqualsWithDelta(150000.0, (float) $transaction->subtotal, 0.01);
    }

    /**
     * Nota lama tidak berubah saat tingkatnya disunting.
     *
     * Sama seperti nama dan harga barang yang sudah di-snapshot: nota yang
     * sudah dicetak wajib tetap terbaca seperti saat pasien menerimanya.
     */
    public function test_editing_a_tier_later_does_not_rewrite_old_receipts(): void
    {
        $tier = $this->tier();
        $transaction = $this->checkout($this->patient(['membership_tier_id' => $tier->id]));

        $tier->update(['name' => 'Platinum', 'discount_value' => 25]);

        $transaction->refresh();

        $this->assertSame('Gold', $transaction->member_tier_name);
        $this->assertEqualsWithDelta(20000.0, (float) $transaction->member_discount_amount, 0.01);
    }

    /**
     * Nota yang dimundurkan memakai keadaan pasien saat itu.
     *
     * Tanpa ini, penjualan bulan lalu yang baru dicatat hari ini ikut kena
     * potongan dari kartu yang baru dibeli minggu ini — dan laporan bulan itu
     * berubah sendiri setelah ditutup.
     */
    public function test_a_backdated_invoice_uses_the_membership_of_that_day(): void
    {
        $patient = $this->patient([
            'membership_tier_id' => $this->tier()->id,
            'member_since' => now()->subDays(3),
        ]);

        $transaction = $this->checkout($patient, [
            'issued_at' => now()->subWeek()->format('Y-m-d H:i:s'),
        ]);

        $this->assertEqualsWithDelta(0.0, (float) $transaction->member_discount_amount, 0.01);
    }

    /**
     * Memeriksa keanggotaan tidak boleh menumpulkan jam transaksi ke tengah
     * malam.
     *
     * Carbon::startOfDay() mengubah objeknya sendiri, bukan mengembalikan
     * salinan. `Patient::activeMembership()` dulu dipanggil langsung dengan
     * `$issuedAt` yang sama persis dipakai menyimpan waktu nota — begitu
     * pemeriksaan keanggotaan selesai, `$issuedAt` ikut terpangkas ke
     * 00:00:00 walau transaksinya dibuat siang atau malam hari. Komisi yang
     * baru berlaku sore itu jadi terlihat belum berlaku, karena jam nota
     * yang seharusnya sore sudah keburu jadi tengah malam.
     */
    public function test_checking_membership_does_not_truncate_the_invoice_time(): void
    {
        $patient = $this->patient(['membership_tier_id' => $this->tier()->id]);

        $transaction = $this->checkout($patient);

        $this->assertNotSame(
            '00:00:00',
            $transaction->fresh()->issued_at->format('H:i:s'),
            'waktu nota terpangkas ke tengah malam saat keanggotaan diperiksa',
        );
    }
}
