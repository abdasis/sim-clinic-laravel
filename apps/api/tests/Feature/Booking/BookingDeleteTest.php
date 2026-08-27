<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Menghapus booking yang batal dibuat.
 *
 * Jadwal yang salah ketik atau dobel perlu bisa dibuang — membatalkannya
 * menyisakan baris merah di kalender yang tidak pernah benar-benar ada.
 *
 * Tapi tidak semua booking boleh hilang: begitu kunjungannya menghasilkan
 * rekam medis, menghapusnya berarti catatan klinis pasien menggantung pada
 * jadwal yang tidak ada lagi.
 */
class BookingDeleteTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'service_id' => Service::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'status' => BookingStatus::Pending,
        ]);
    }

    public function test_a_plain_booking_can_be_deleted(): void
    {
        $booking = $this->makeBooking();

        $this->deleteJson($this->tenantUrl("bookings/{$booking->id}"))->assertOk();

        $this->assertSame(0, Booking::query()->count());
    }

    /**
     * Booking yang sudah punya rekam medis tidak boleh dihapus. Foreign
     * key-nya memang menahan, tapi yang sampai ke pengguna harus penolakan
     * yang bisa dibaca — bukan galat basis data mentah.
     */
    public function test_a_booking_with_a_medical_record_is_refused_readably(): void
    {
        $booking = $this->makeBooking();

        MedicalRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'patient_id' => $booking->patient_id,
            'author_id' => auth()->id(),
            'anamnesis' => 'Kulit kering',
        ]);

        $this->deleteJson($this->tenantUrl("bookings/{$booking->id}"))
            ->assertStatus(422);

        $this->assertSame(1, Booking::query()->count());
    }

    /** Penolakannya menyebut alasannya, bukan sekadar gagal. */
    public function test_the_refusal_explains_why(): void
    {
        $booking = $this->makeBooking();

        MedicalRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'patient_id' => $booking->patient_id,
            'author_id' => auth()->id(),
            'anamnesis' => 'Kulit kering',
        ]);

        $message = $this->deleteJson($this->tenantUrl("bookings/{$booking->id}"))
            ->assertStatus(422)->json('message');

        $this->assertStringContainsString('rekam medis', $message);
        $this->assertStringContainsString('Batalkan', $message);
    }

    /**
     * Booking yang sudah dibayar juga ditahan. Foreign key notanya justru
     * tidak menahan apa pun — notanya tetap ada tapi diam-diam kehilangan
     * tautan ke kunjungannya, dan uang yang sudah berpindah berarti
     * kunjungannya sungguh terjadi.
     */
    public function test_a_paid_booking_is_refused(): void
    {
        $booking = $this->makeBooking();

        Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $booking->patient_id,
            'booking_id' => $booking->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => 300000,
            'paid_amount' => 300000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => now(),
        ]);

        $this->deleteJson($this->tenantUrl("bookings/{$booking->id}"))
            ->assertStatus(422);

        $this->assertSame(1, Booking::query()->count());
    }

    /** Pengingat WhatsApp-nya ikut dibatalkan, tidak tertinggal di antrean. */
    public function test_the_whatsapp_reminder_is_cancelled_too(): void
    {
        $booking = $this->makeBooking();

        BookingReminder::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'remind_at' => now()->addHours(2),
            'reminder_offset_minutes' => 60,
            'status' => 'pending',
        ]);

        $this->deleteJson($this->tenantUrl("bookings/{$booking->id}"))->assertOk();

        $this->assertSame(0, BookingReminder::query()->count());
    }

    /** Jejaknya tetap tercatat, jadi penghapusan bukan peristiwa tanpa saksi. */
    public function test_the_deletion_is_recorded_in_the_audit_log(): void
    {
        $this->deleteJson($this->tenantUrl("bookings/{$this->makeBooking()->id}"))
            ->assertOk();

        $this->assertNotNull(Activity::where('event', 'booking.deleted')->first());
    }

    /**
     * Layar jadwal membawa penandanya, jadi tombolnya tidak menjanjikan
     * sesuatu yang nanti ditolak server.
     */
    public function test_the_schedule_marks_which_bookings_may_be_deleted(): void
    {
        $plain = $this->makeBooking();
        $recorded = $this->makeBooking();

        MedicalRecord::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $recorded->id,
            'patient_id' => $recorded->patient_id,
            'author_id' => auth()->id(),
            'anamnesis' => 'Kulit kering',
        ]);

        $rows = collect(
            $this->getJson($this->tenantUrl('bookings/schedule').'?'.http_build_query([
                'from' => now()->addDay()->toDateString(),
                'to' => now()->addDay()->toDateString(),
            ]))->assertOk()->json('data'),
        )->keyBy('id');

        $this->assertTrue($rows[$plain->id]['can_delete']);
        $this->assertFalse($rows[$recorded->id]['can_delete']);
    }

    /** Peran tanpa wewenang mengelola booking tidak boleh menghapus. */
    public function test_a_role_without_manage_rights_is_refused(): void
    {
        $booking = $this->makeBooking();

        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->deleteJson($this->tenantUrl("bookings/{$booking->id}"))
            ->assertForbidden();

        $this->assertSame(1, Booking::query()->count());
    }

    /** Booking klinik lain tidak bisa dihapus dari sini. */
    public function test_another_clinics_booking_is_out_of_reach(): void
    {
        $booking = $this->makeBooking();

        $other = $this->createTenant('klinik-lain');
        app()->instance('tenant', $this->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $this->deleteJson('/api/'.$other->slug."/clinic/bookings/{$booking->id}")
            ->assertNotFound();

        // Dihitung tanpa scope: permintaan barusan mengikat tenant tetangga
        // di container, jadi kueri bertenant di sini akan menghitung nol
        // bahkan bila bookingnya masih utuh.
        $this->assertSame(1, Booking::withoutGlobalScopes()->count());
    }
}
