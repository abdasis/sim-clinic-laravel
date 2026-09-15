<?php

namespace Tests\Feature\Loyalty;

use App\Actions\Transaction\PayTransactionAction;
use App\Enums\ClinicRole;
use App\Models\LoyaltySetting;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Tarif poin milik tiap klinik, bukan konstanta yang cuma bisa berubah lewat
 * rilis.
 *
 * Yang dijaga di sini bukan cuma bahwa angkanya bisa disimpan, melainkan
 * bahwa mengubahnya benar-benar mengubah hitungan berikutnya — dan tidak
 * menyentuh nota yang sudah terbit.
 */
class LoyaltySettingTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return [
            'earn_rate' => 50000,
            'redeem_rate' => 500,
            'min_redeem' => 5,
            ...$overrides,
        ];
    }

    private function sell(float $price, int $points = 0): Transaction
    {
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial '.uniqid(),
            'price' => $price,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->patient()->id,
            'items' => [['service_id' => $service->id, 'qty' => 1]],
            ...($points > 0 ? ['points_redeemed' => $points] : []),
        ])->assertCreated();

        return Transaction::query()->latest('id')->first();
    }

    private ?Patient $patient = null;

    private function patient(): Patient
    {
        return $this->patient ??= Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_member' => true,
            'loyalty_points' => 100,
        ]);
    }

    /** Angka uang pulang sebagai float atau string tergantung driver. */
    private function assertRate(float $expected, mixed $actual): void
    {
        $this->assertEqualsWithDelta($expected, (float) $actual, 0.01);
    }

    /** Klinik yang belum pernah menyetel tetap punya jawaban, bukan null. */
    public function test_a_clinic_that_never_set_rates_still_reads_defaults(): void
    {
        $this->actingAsClinicUser();

        $data = $this->getJson($this->tenantUrl('loyalty-settings'))
            ->assertOk()
            ->json('data');

        $this->assertRate(10000.0, $data['earn_rate']);
        $this->assertRate(1000.0, $data['redeem_rate']);
        $this->assertSame(10, $data['min_redeem']);
    }

    public function test_an_admin_can_change_the_rates(): void
    {
        $this->actingAsClinicUser();

        $data = $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload())
            ->assertOk()
            ->json('data');

        $this->assertRate(50000.0, $data['earn_rate']);
        $this->assertRate(500.0, $data['redeem_rate']);
        $this->assertDatabaseHas('loyalty_settings', ['tenant_id' => $this->tenant->id]);
    }

    /** Menyimpan dua kali tidak melahirkan tarif kedua yang sama sahnya. */
    public function test_saving_twice_keeps_one_row(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload())->assertOk();

        $data = $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload(['earn_rate' => 25000]))
            ->assertOk()
            ->json('data');

        $this->assertRate(25000.0, $data['earn_rate']);
        $this->assertSame(1, LoyaltySetting::query()->count());
    }

    /** Tarif baru langsung dipakai nota berikutnya, bukan nanti setelah rilis. */
    public function test_a_new_earn_rate_changes_the_points_given(): void
    {
        $this->actingAsClinicUser();
        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload())->assertOk();

        $transaction = $this->sell(200000);

        app(PayTransactionAction::class)->handle($transaction, [
            'method' => 'cash',
            'amount' => 200000,
            'paid_at' => now(),
        ]);

        // floor(200.000 / 50.000) = 4 poin, bukan 20 seperti tarif bawaan.
        $this->assertSame(4, $transaction->fresh()->points_earned);
    }

    public function test_a_new_redeem_rate_changes_what_a_point_is_worth(): void
    {
        $this->actingAsClinicUser();
        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload())->assertOk();

        $transaction = $this->sell(200000, points: 20);

        // 20 poin x Rp500 = Rp10.000, bukan Rp20.000 seperti tarif bawaan.
        $this->assertEqualsWithDelta(10000.0, (float) $transaction->points_redeemed_amount, 0.01);
        $this->assertEqualsWithDelta(190000.0, (float) $transaction->subtotal, 0.01);
    }

    /** Minimum tukar pun ikut setelan klinik, bukan angka tetap. */
    public function test_the_minimum_redemption_follows_the_setting(): void
    {
        $this->actingAsClinicUser();
        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload())->assertOk();

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 200000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);

        // 5 poin ditolak dengan bawaan (minimum 10), diterima dengan setelan ini.
        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $this->patient()->id,
            'items' => [['service_id' => $service->id, 'qty' => 1]],
            'points_redeemed' => 5,
        ])->assertCreated();
    }

    /**
     * Nota yang sudah terbit tidak boleh ikut berubah angkanya.
     *
     * Pasien memegang kertas dengan potongan tertentu; tarif yang diubah
     * seminggu kemudian tidak boleh menulis ulang apa yang sudah dibayar.
     */
    public function test_changing_rates_does_not_rewrite_past_invoices(): void
    {
        $this->actingAsClinicUser();

        $transaction = $this->sell(200000, points: 20);
        $this->assertEqualsWithDelta(20000.0, (float) $transaction->points_redeemed_amount, 0.01);

        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload())->assertOk();

        $this->assertEqualsWithDelta(
            20000.0,
            (float) $transaction->fresh()->points_redeemed_amount,
            0.01,
        );
        $this->assertEqualsWithDelta(180000.0, (float) $transaction->fresh()->subtotal, 0.01);
    }

    /**
     * Poin yang menebus lebih besar daripada belanja yang menghasilkannya
     * membuat tiap kunjungan mencetak potongan untuk kunjungan berikutnya.
     */
    public function test_a_point_worth_more_than_it_costs_is_refused(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload([
            'earn_rate' => 10000,
            'redeem_rate' => 10000,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('redeem_rate');
    }

    public function test_a_zero_rate_is_refused(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload(['earn_rate' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('earn_rate');
    }

    /** Kasir perlu membacanya untuk perkiraan di layar bayar, bukan mengubahnya. */
    public function test_a_cashier_may_read_but_not_change_the_rates(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('loyalty-settings'))->assertOk();
        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload())->assertForbidden();
    }

    /** Terapis tidak berurusan dengan tarif poin sama sekali. */
    public function test_a_therapist_cannot_read_the_rates(): void
    {
        $this->actingAsClinicUser(ClinicRole::Therapist);

        $this->getJson($this->tenantUrl('loyalty-settings'))->assertForbidden();
    }

    /** Tarif klinik lain tidak boleh ikut berubah. */
    public function test_rates_are_scoped_to_one_clinic(): void
    {
        $this->actingAsClinicUser();
        $this->putJson($this->tenantUrl('loyalty-settings'), $this->payload())->assertOk();

        $other = $this->createTenant('klinik-lain');
        $this->actingAsClinicUser(ClinicRole::Admin, $other);

        $this->assertRate(
            10000.0,
            $this->getJson($this->tenantUrl('loyalty-settings', $other))
                ->assertOk()
                ->json('data.earn_rate'),
        );
    }
}
