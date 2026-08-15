<?php

namespace Tests\Feature\Broadcast;

use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Models\Broadcast;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\WhatsappSetting;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Broadcast menjangkau pasien sungguhan lewat nomor WhatsApp mereka, jadi
 * yang diuji adalah siapa yang masuk daftar, pesan apa yang mereka terima,
 * dan apa yang terjadi saat gateway menolak.
 */
class BroadcastApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function patient(string $name, ?string $phone = '081234567890', ?string $whatsapp = null): Patient
    {
        return Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'phone' => $phone ?? '',
            'whatsapp' => $whatsapp,
        ]);
    }

    private function visit(Patient $patient, string $date, ?Service $service = null): Transaction
    {
        $service ??= Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => 100000,
            'paid_amount' => 100000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => $date.' 10:00:00',
        ]);

        $transaction->items()->create([
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        return $transaction;
    }

    public function test_phone_normalization_handles_indonesian_formats(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::normalize('081234567890'));
        $this->assertSame('6281234567890', PhoneNumber::normalize('+62 812-3456-7890'));
        $this->assertSame('6281234567890', PhoneNumber::normalize('81234567890'));
        $this->assertSame('6281234567890', PhoneNumber::normalize('62081234567890'));
        $this->assertNull(PhoneNumber::normalize('021555'));
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize(null));
    }

    public function test_creates_broadcast_with_rendered_messages(): void
    {
        $this->actingAsClinicUser();

        $patient = $this->patient('Dessy Natalia');
        $this->visit($patient, now()->subDays(40)->toDateString());

        $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Pengingat Facial',
            'message' => 'Halo {nama}, sudah {hari_sejak_kunjungan} hari sejak {layanan_terakhir}. Salam, {klinik}!',
            'audience' => 'inactive',
            'audience_params' => ['days' => 30],
        ])->assertCreated()->assertJsonPath('data.recipients_total', 1);

        $recipient = Broadcast::first()->recipients()->first();

        $this->assertSame('6281234567890', $recipient->phone);
        $this->assertStringContainsString('Halo Dessy Natalia, sudah 40 hari', $recipient->message);
        $this->assertStringContainsString('Salam, '.$this->tenant->name.'!', $recipient->message);
        $this->assertStringNotContainsString('{', $recipient->message);
    }

    public function test_inactive_audience_excludes_recent_and_never_visited(): void
    {
        $this->actingAsClinicUser();

        $overdue = $this->patient('Lama Tidak Datang', '081111111111');
        $this->visit($overdue, now()->subDays(60)->toDateString());

        $recent = $this->patient('Baru Saja Datang', '082222222222');
        $this->visit($recent, now()->subDays(3)->toDateString());

        // Belum pernah transaksi — tidak ada yang perlu diingatkan.
        $this->patient('Belum Pernah', '083333333333');

        $preview = $this->getJson($this->tenantUrl('broadcasts/audience-preview?audience=inactive&days=30'))
            ->assertOk();

        $preview->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.sample.0.name', 'Lama Tidak Datang');
    }

    public function test_service_audience_filters_by_service_taken(): void
    {
        $this->actingAsClinicUser();

        $facial = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Facial Glow']);
        $botox = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Botox']);

        $facialPatient = $this->patient('Suka Facial', '081111111111');
        $this->visit($facialPatient, now()->subDays(10)->toDateString(), $facial);

        $botoxPatient = $this->patient('Suka Botox', '082222222222');
        $this->visit($botoxPatient, now()->subDays(10)->toDateString(), $botox);

        $this->getJson($this->tenantUrl('broadcasts/audience-preview?audience=service&service_id='.$facial->id))
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.sample.0.name', 'Suka Facial');
    }

    public function test_duplicate_phones_and_missing_phones_are_handled(): void
    {
        $this->actingAsClinicUser();

        $this->patient('Ibu', '081111111111');
        $this->patient('Anak Nomor Sama', '0811 1111 1111');
        $this->patient('Tanpa Nomor', '');

        $this->getJson($this->tenantUrl('broadcasts/audience-preview?audience=all'))
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.without_phone', 1);
    }

    public function test_whatsapp_column_beats_phone_column(): void
    {
        $this->actingAsClinicUser();

        $this->patient('Dua Nomor', '081111111111', '089999999999');

        $preview = $this->getJson($this->tenantUrl('broadcasts/audience-preview?audience=all'))->assertOk();

        $this->assertSame('6289999999999', $preview->json('data.sample.0.phone'));
    }

    public function test_manual_marking_updates_status(): void
    {
        $this->actingAsClinicUser();
        $this->patient('Dessy', '081111111111');

        $broadcast = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo', 'message' => 'Halo {nama}', 'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $recipient = Broadcast::find($broadcast)->recipients()->first();

        $this->patchJson($this->tenantUrl("broadcasts/{$broadcast}/recipients/{$recipient->id}"), ['status' => 'sent'])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->assertNotNull($recipient->fresh()->sent_at);
    }

    public function test_gateway_send_marks_sent_and_failed_individually(): void
    {
        $this->actingAsClinicUser();

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'driver' => 'gateway',
            'api_url' => 'https://gateway.test/send',
            'api_token' => 'secret-token',
        ]);

        $this->patient('Sukses', '081111111111');
        $this->patient('Gagal', '082222222222');

        $broadcast = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo', 'message' => 'Halo {nama}', 'audience' => 'all',
        ])->assertCreated()->json('data.id');

        // Nomor pertama sukses, kedua ditolak gateway.
        Http::fake([
            'gateway.test/*' => Http::sequence()
                ->push(['status' => true], 200)
                ->push(['status' => false], 500),
        ]);

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast}/send"))
            ->assertOk()
            ->assertJsonPath('data.sent', 1)
            ->assertJsonPath('data.failed', 1);

        $statuses = Broadcast::find($broadcast)->recipients()->orderBy('id')->pluck('status')->map->value->all();
        $this->assertSame(['sent', 'failed'], $statuses);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'secret-token')
            && $request['target'] === '6281111111111');
    }

    public function test_gateway_send_rejected_when_not_configured(): void
    {
        $this->actingAsClinicUser();
        $this->patient('Dessy', '081111111111');

        $broadcast = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo', 'message' => 'Halo', 'audience' => 'all',
        ])->assertCreated()->json('data.id');

        $this->postJson($this->tenantUrl("broadcasts/{$broadcast}/send"))->assertStatus(422);
    }

    public function test_settings_keep_token_when_blank(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/settings'), [
            'driver' => 'gateway',
            'api_url' => 'https://gateway.test/send',
            'api_token' => 'rahasia',
        ])->assertOk()->assertJsonPath('data.has_token', true);

        // Simpan ulang tanpa token — token lama tidak boleh tercabut.
        $this->putJson($this->tenantUrl('broadcasts/settings'), [
            'driver' => 'gateway',
            'api_url' => 'https://gateway.test/send',
        ])->assertOk()->assertJsonPath('data.has_token', true);

        $this->assertSame('rahasia', WhatsappSetting::first()->api_token);
    }

    public function test_non_admin_cannot_touch_broadcasts(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('broadcasts'))->assertForbidden();
        $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'x', 'message' => 'y', 'audience' => 'all',
        ])->assertForbidden();
    }
}
