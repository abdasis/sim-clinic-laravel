<?php

namespace Tests\Feature\Broadcast;

use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastReminderSetting;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\ReminderRule;
use App\Models\Service;
use App\Models\Transaction;
use App\Support\AutoReminderEngine;
use App\Support\ReminderEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Dua mesin pengingat berjalan tiap pagi berselang setengah jam: yang satu
 * dari aturan per layanan, yang satu follow-up otomatis. Masing-masing hanya
 * memeriksa jejaknya sendiri, jadi pasien yang memenuhi kedua ambang menerima
 * dua pesan untuk kunjungan yang sama.
 */
class DoubleReminderTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function patientWhoVisited(int $daysAgo): Patient
    {
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '081234567890',
            'whatsapp_opt_in' => true,
        ]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-PENGINGAT-1',
            'subtotal' => 100_000,
            'paid_amount' => 100_000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => now()->subDays($daysAgo),
        ]);

        $transaction->items()->create([
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price' => 100_000,
            'qty' => 1,
            'subtotal' => 100_000,
        ]);

        // Kunjungan nyata meninggalkan dua jejak sekaligus: nota dan rekam
        // medis. Mesin yang satu membaca nota, yang satu membaca rekam medis.
        $booking = Booking::factory()->done()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->subDays($daysAgo),
        ]);

        $record = MedicalRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
            'author_id' => auth()->id(),
            'anamnesis' => 'Kunjungan rutin.',
        ]);
        $record->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        ReminderRule::create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'days_after' => $daysAgo,
            'is_active' => true,
        ]);

        BroadcastReminderSetting::create([
            'tenant_id' => $this->tenant->id,
            'days' => $daysAgo,
            'is_active' => true,
        ]);

        return $patient;
    }

    public function test_a_patient_is_reminded_once_even_when_both_engines_run(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $patient = $this->patientWhoVisited(40);

        // Persis jadwal harian: 08.00 lalu 08.30.
        app(ReminderEngine::class)->run();
        app(AutoReminderEngine::class)->run();

        $sent = BroadcastRecipient::query()->where('patient_id', $patient->id)->count();

        $this->assertSame(1, $sent, 'pasien menerima lebih dari satu pengingat untuk kunjungan yang sama');
    }

    public function test_order_does_not_matter(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $patient = $this->patientWhoVisited(40);

        app(AutoReminderEngine::class)->run();
        app(ReminderEngine::class)->run();

        $this->assertSame(
            1,
            BroadcastRecipient::query()->where('patient_id', $patient->id)->count(),
        );
    }
}
