<?php

namespace Tests\Feature\Chatbot;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\CompanyProfileSetting;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Support\ChatTools;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Kemampuan booking chatbot: cek ketersediaan, jam operasional, promo, serta
 * lihat dan batalkan booking sendiri.
 *
 * Diuji lewat ChatTools::execute, bukan langsung ke Action-nya, karena yang
 * dipakai AI adalah pintu itu — termasuk penjaga "pasien harus dikenal" yang
 * memang hidup di sana.
 */
class ChatbotAvailabilityTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Service $service;

    private User $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'duration_minutes' => 60,
        ]);

        $this->therapist = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Terapis Uji',
            'email' => 'terapis-slot@uji.test',
            'password' => bcrypt('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);
    }

    /** Rabu depan pukul 10:00 — hari kerja, supaya jam operasional tidak mengganggu. */
    private function slot(): Carbon
    {
        return Carbon::now()->next(Carbon::WEDNESDAY)->setTime(10, 0);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function check(array $arguments): array
    {
        return ChatTools::execute('check_availability', [
            'service_id' => $this->service->id,
            'start_at' => $this->slot()->format('Y-m-d H:i'),
            ...$arguments,
        ], null);
    }

    private function bookThatSlot(): Booking
    {
        return Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'service_id' => $this->service->id,
            'assignee_id' => $this->therapist->id,
            'start_at' => $this->slot(),
            'end_at' => $this->slot()->addHour(),
            'status' => BookingStatus::Confirmed,
        ]);
    }

    public function test_a_free_slot_is_reported_available(): void
    {
        $result = $this->check(['assignee_id' => $this->therapist->id]);

        $this->assertTrue($result['available']);
        $this->assertNull($result['reason']);
        $this->assertSame([], $result['conflicts']);
    }

    /** Inti fiturnya: jam yang sudah terisi tidak boleh ditawarkan. */
    public function test_a_taken_slot_is_reported_unavailable_with_the_conflict(): void
    {
        $this->bookThatSlot();

        $result = $this->check(['assignee_id' => $this->therapist->id]);

        $this->assertFalse($result['available']);
        $this->assertSame(__('chatbot.availability_staff_busy'), $result['reason']);
        $this->assertSame('Terapis Uji', $result['conflicts'][0]['staff_name']);
    }

    /** Tanpa menyebut staf, yang dikembalikan adalah siapa saja yang bebas. */
    public function test_without_a_named_staff_it_returns_who_is_free(): void
    {
        $result = $this->check([]);

        $this->assertTrue($result['available']);
        $this->assertSame([['id' => $this->therapist->id, 'name' => 'Terapis Uji']], $result['available_staff']);

        $this->bookThatSlot();

        $after = $this->check([]);

        $this->assertFalse($after['available']);
        $this->assertSame([], $after['available_staff']);
        $this->assertSame(__('chatbot.availability_no_staff'), $after['reason']);
    }

    /** Booking yang sudah dibatalkan tidak boleh ikut menahan slotnya. */
    public function test_a_cancelled_booking_frees_the_slot_again(): void
    {
        $this->bookThatSlot()->update(['status' => BookingStatus::Cancelled]);

        $this->assertTrue($this->check(['assignee_id' => $this->therapist->id])['available']);
    }

    public function test_a_closed_day_is_refused(): void
    {
        CompanyProfileSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'operating_hours' => $this->hours(wednesday: false),
        ]);

        $result = $this->check(['assignee_id' => $this->therapist->id]);

        $this->assertFalse($result['available']);
        $this->assertSame(__('chatbot.availability_clinic_closed'), $result['reason']);
    }

    /** Jam buka juga membatasi jamnya, bukan cuma harinya. */
    public function test_a_time_outside_opening_hours_is_refused(): void
    {
        CompanyProfileSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'operating_hours' => $this->hours(open: '13:00', close: '17:00'),
        ]);

        $result = $this->check(['assignee_id' => $this->therapist->id]);

        $this->assertFalse($result['available']);
        $this->assertSame(__('chatbot.availability_clinic_closed'), $result['reason']);
    }

    /**
     * create_booking tetap menolak sendiri walau AI melewatkan
     * check_availability — "disuruh memanggil" bukan jaminan.
     */
    public function test_create_booking_refuses_a_closed_day_on_its_own(): void
    {
        CompanyProfileSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'operating_hours' => $this->hours(wednesday: false),
        ]);

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id, 'whatsapp' => '6281234567890']);

        $result = ChatTools::execute('create_booking', [
            'service_id' => $this->service->id,
            'assignee_id' => $this->therapist->id,
            'start_at' => $this->slot()->format('Y-m-d H:i'),
        ], $patient);

        $this->assertSame(__('chatbot.booking_outside_hours'), $result['error']);
        $this->assertSame(0, Booking::query()->count());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function hours(bool $wednesday = true, string $open = '09:00', string $close = '17:00'): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];

        foreach ($days as $day) {
            $hours[$day] = [
                'is_open' => $day === 'wednesday' ? $wednesday : true,
                'open' => $open,
                'close' => $close,
            ];
        }

        return $hours;
    }
}
