<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendBookingConfirmationJob;
use App\Models\Booking;
use App\Models\Broadcast;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Models\ReminderRule;
use App\Models\Service;
use App\Models\Transaction;
use App\Support\ClinicIdentity;
use App\Support\ReminderEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pengingat dan konfirmasi booking berjalan lewat gateway WhatsApp internal.
 *
 * Tidak ada lagi template resmi Meta yang harus disetujui lebih dulu: isi
 * pesan diambil dari MessageTemplate milik klinik (atau teks bawaan), lalu
 * dirender per penerima. Yang dijaga di sini: pesan tetap terisi walau klinik
 * belum pernah memilih template Meta, dan konfirmasi booking benar-benar
 * berangkat ke endpoint gateway.
 */
class ReminderGatewayTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function paidVisit(Patient $patient, Service $service, string $date): void
    {
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
    }

    public function test_reminder_rows_render_clinic_template_without_meta_template(): void
    {
        $this->actingAsClinicUser();
        Http::fake();

        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
        ]);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Desi',
            'whatsapp' => '081111111111',
        ]);
        $this->paidVisit($patient, $service, now()->subDays(40)->toDateString());

        $body = 'Halo {nama}, jadwal {tanggal_terakhir} di {klinik}';
        $template = MessageTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pengingat facial',
            'body' => $body,
        ]);

        ReminderRule::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'days_after' => 30,
            'message_template_id' => $template->id,
            'is_active' => true,
        ]);

        (new ReminderEngine)->run();

        $broadcast = Broadcast::query()->where('kind', 'reminder')->firstOrFail();
        $this->assertSame($body, $broadcast->template['body'] ?? null);

        $recipient = $broadcast->recipients()->firstOrFail();

        $this->assertSame(
            'Halo Desi, jadwal '
                .Carbon::parse(now()->subDays(40)->toDateString())->translatedFormat('d F Y')
                .' di '.ClinicIdentity::displayName(),
            $recipient->message,
        );

        // Tidak ada satu pun panggilan ke jalur pengiriman lama.
        Http::assertNothingSent();
    }

    public function test_a_broadcast_without_a_clinic_template_still_gets_the_default_text(): void
    {
        $this->actingAsClinicUser();
        Http::fake();

        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
        ]);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Desi',
            'whatsapp' => '081111111111',
        ]);
        $this->paidVisit($patient, $service, now()->subDays(40)->toDateString());

        ReminderRule::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'days_after' => 30,
            'is_active' => true,
        ]);

        (new ReminderEngine)->run();

        $broadcast = Broadcast::query()->where('kind', 'reminder')->firstOrFail();
        $this->assertSame([], $broadcast->template['params']);

        $recipient = $broadcast->recipients()->firstOrFail();

        $this->assertStringContainsString('Desi', $recipient->message);
        $this->assertStringContainsString('Facial', $recipient->message);
        Http::assertNothingSent();
    }

    public function test_booking_confirmation_is_sent_via_gateway(): void
    {
        $this->actingAsClinicUser();
        $this->configureWhatsappGateway();

        Http::fake([
            'gateway.test/*' => Http::response([
                'success' => true,
                'data' => ['messageId' => 'uuid-booking', 'status' => 'QUEUED'],
            ], 202),
        ]);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 300000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Ani Lestari',
                'whatsapp' => '081234567890',
            ])->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'status' => BookingStatus::Confirmed,
        ]);

        (new SendBookingConfirmationJob($this->tenant->id, $booking->id))->handle();

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/api/v1/messages/send')) {
                return false;
            }

            return $request['to'] === '6281234567890'
                && str_contains($request['text'], 'Ani Lestari')
                && str_contains($request['text'], 'konfirmasi');
        });
    }
}
