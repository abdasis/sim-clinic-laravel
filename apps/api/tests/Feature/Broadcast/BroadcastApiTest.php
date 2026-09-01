<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastStatus;
use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Jobs\SendBroadcastRecipientJob;
use App\Models\Broadcast;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\WahaSetting;
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

    private function patient(string $name, ?string $whatsapp = '081234567890'): Patient
    {
        return Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'whatsapp' => $whatsapp ?? '',
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

    /**
     * Jumlah hari dihitung per hari kalender. Sebelumnya selisihnya dihitung
     * per 24 jam lalu dipotong, sehingga pesan yang sama berbunyi "40 hari"
     * bila dikirim sore dan "39 hari" bila dikirim pagi — dan bisa meleset
     * dari ambang aturan pengingat yang memicunya.
     */
    public function test_days_since_visit_does_not_depend_on_send_time(): void
    {
        $this->actingAsClinicUser();

        foreach (['06:00:00', '23:30:00'] as $sendTime) {
            $this->travelTo('2026-08-16 '.$sendTime);

            $patient = $this->patient('Dessy '.$sendTime, '08'.substr((string) crc32($sendTime), 0, 10));
            $this->visit($patient, '2026-07-07');

            $this->postJson($this->tenantUrl('broadcasts'), [
                'title' => 'Pengingat '.$sendTime,
                'message' => 'Sudah {hari_sejak_kunjungan} hari.',
                'audience' => 'inactive',
                'audience_params' => ['days' => 30],
            ])->assertCreated();

            $message = Broadcast::query()->latest('id')->first()->recipients()
                ->where('patient_id', $patient->id)->first()->message;

            $this->assertSame('Sudah 40 hari.', $message, "dikirim pukul {$sendTime}");
        }

        $this->travelBack();
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
            ->assertJsonPath('data.recipients.0.name', 'Lama Tidak Datang');
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
            ->assertJsonPath('data.recipients.0.name', 'Suka Facial');
    }

    public function test_duplicate_and_missing_numbers_are_handled(): void
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

    public function test_failed_job_marks_recipient_failed_with_error(): void
    {
        $this->actingAsClinicUser();

        // Server WAHA milik platform, nama sesi milik klinik. Keduanya harus
        // terisi supaya klien terbentuk.
        WahaSetting::create([
            'base_url' => 'https://waha.test',
            'api_key' => 'secret-token',
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
        ]);

        $this->patient('Gagal', '082222222222');

        $broadcast = $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo', 'message' => 'Halo {nama}', 'audience' => 'all',
        ])->assertCreated()->json('data.id');

        Broadcast::find($broadcast)->update(['status' => BroadcastStatus::Sending]);
        $recipient = Broadcast::find($broadcast)->recipients()->first();

        // Sesinya sehat, kirimannya sendiri yang ditolak — kegagalan milik
        // nomor ini saja. Gateway yang mati punya jalur lain (campaign-nya
        // dijeda, penerimanya dibiarkan menunggu); lihat
        // BroadcastGatewayFailureTest.
        Http::fake([
            'waha.test/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
            'waha.test/api/sendText' => Http::response(['message' => 'Number not registered on WhatsApp'], 422),
        ]);

        // Jalankan percobaan terakhir langsung: job harus menandai gagal
        // beserta pesan galatnya, bukan melempar tanpa jejak.
        $job = new SendBroadcastRecipientJob($recipient->id, $this->tenant->id);
        // attempts() dari queue tidak tersedia di luar worker; simulasikan
        // percobaan terakhir dengan menurunkan tries ke 1.
        $job->tries = 1;
        $job->handle();

        $fresh = $recipient->fresh();
        $this->assertSame('failed', $fresh->status->value);
        $this->assertStringContainsString('Number not registered', (string) $fresh->error);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sendText')
            && $request->hasHeader('X-Api-Key', 'secret-token')
            && $request['chatId'] === '6282222222222@c.us'
            && $request['session'] === 'klinik-uji');
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

    public function test_settings_store_the_session_name(): void
    {
        $this->actingAsClinicUser();

        $this->putJson($this->tenantUrl('broadcasts/settings'), [
            'session' => 'klinik-satu',
        ])->assertOk()->assertJsonPath('data.session', 'klinik-satu');

        $this->assertSame('klinik-satu', WhatsappSetting::first()->session);
    }

    public function test_settings_reject_a_session_name_waha_cannot_use(): void
    {
        $this->actingAsClinicUser();

        // WAHA hanya menerima huruf kecil, angka, dan strip.
        $this->putJson($this->tenantUrl('broadcasts/settings'), [
            'session' => 'Klinik Satu!',
        ])->assertStatus(422)->assertJsonValidationErrors('session');
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
