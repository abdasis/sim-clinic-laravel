<?php

namespace Tests\Feature\Chatbot;

use App\Enums\ClinicRole;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use App\Models\CompanyProfileSetting;
use App\Models\Patient;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Penutup percakapan yang ditinggal diam pasien.
 *
 * Yang dijaga di sini bukan kalimatnya, melainkan kapan ia boleh keluar.
 * Salah sedikit, klinik mengirim perpisahan ke pasien yang justru sedang
 * menunggu jawaban — atau mengirimnya berulang setiap lima menit.
 */
class IdleChatClosingTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser(ClinicRole::Admin);

        ChatbotSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'closing_idle_minutes' => 10,
        ]);

        WahaSetting::create(['base_url' => 'https://waha.uji', 'api_key' => 'rahasia']);
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);

        Http::fake(['waha.uji/*' => Http::response(['id' => 'true_628@c.us'])]);
    }

    /**
     * Satu baris percakapan, ditempatkan sekian menit yang lalu.
     */
    private function message(string $phone, string $direction, string $content, int $minutesAgo, ?string $role = null): ChatMessage
    {
        return ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sender_phone' => $phone,
            'direction' => $direction,
            'role' => $role ?? ($direction === 'in' ? 'user' : 'assistant'),
            'content' => $content,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function sweep(): void
    {
        $this->artisan('clinic:close-idle-chats')->assertSuccessful();
    }

    private function closingFor(string $phone): ?ChatMessage
    {
        return ChatMessage::query()
            ->where('sender_phone', $phone)
            ->where('is_closing', true)
            ->first();
    }

    public function test_a_conversation_the_bot_spoke_last_in_gets_closed(): void
    {
        $this->message('6281111111111', 'in', 'halo', 30);
        $this->message('6281111111111', 'out', 'Baik, saya bantu ya.', 25);

        $this->sweep();

        $closing = $this->closingFor('6281111111111');

        $this->assertNotNull($closing);
        $this->assertStringContainsString('saya tutup dulu', $closing->content);
        $this->assertStringContainsString($this->tenant->name, $closing->content);
    }

    public function test_a_conversation_waiting_on_the_bot_is_left_alone(): void
    {
        $this->message('6282222222222', 'out', 'Baik, saya bantu ya.', 30);
        $this->message('6282222222222', 'in', 'jam berapa buka?', 25);

        $this->sweep();

        // Pesan terakhir dari pasien berarti botnya yang belum menjawab.
        // Menutupnya sama saja dengan menyembunyikan kegagalan.
        $this->assertNull($this->closingFor('6282222222222'));
    }

    public function test_a_conversation_is_never_closed_twice(): void
    {
        $this->message('6283333333333', 'in', 'halo', 30);
        $this->message('6283333333333', 'out', 'Baik, saya bantu ya.', 25);

        $this->sweep();
        $this->sweep();

        $this->assertSame(1, ChatMessage::query()
            ->where('sender_phone', '6283333333333')
            ->where('is_closing', true)
            ->count());
    }

    public function test_a_conversation_that_just_went_quiet_keeps_waiting(): void
    {
        $this->message('6284444444444', 'in', 'halo', 6);
        $this->message('6284444444444', 'out', 'Baik, saya bantu ya.', 5);

        $this->sweep();

        $this->assertNull($this->closingFor('6284444444444'));
    }

    public function test_conversations_older_than_the_window_are_not_reopened(): void
    {
        $this->message('6285555555555', 'in', 'halo', 400);
        $this->message('6285555555555', 'out', 'Baik, saya bantu ya.', 395);

        $this->sweep();

        // Klinik yang baru menyalakan fiturnya tidak boleh mengirim
        // perpisahan ke percakapan yang sudah basi berjam-jam.
        $this->assertNull($this->closingFor('6285555555555'));
    }

    public function test_nothing_is_sent_when_the_feature_is_off(): void
    {
        ChatbotSetting::query()->update(['closing_idle_minutes' => null]);

        $this->message('6286666666666', 'in', 'halo', 30);
        $this->message('6286666666666', 'out', 'Baik, saya bantu ya.', 25);

        $this->sweep();

        $this->assertNull($this->closingFor('6286666666666'));
    }

    public function test_nothing_is_sent_when_the_chatbot_is_off(): void
    {
        ChatbotSetting::query()->update(['is_active' => false]);

        $this->message('6287777777777', 'in', 'halo', 30);
        $this->message('6287777777777', 'out', 'Baik, saya bantu ya.', 25);

        $this->sweep();

        $this->assertNull($this->closingFor('6287777777777'));
    }

    public function test_nothing_is_sent_while_the_clinic_is_closed(): void
    {
        // Jam operasional yang tidak pernah mencakup sekarang: hari ini tutup.
        CompanyProfileSetting::query()->create([
            'tenant_id' => $this->tenant->id,
            'operating_hours' => [strtolower(now()->format('l')) => ['is_open' => false]],
        ]);

        $this->message('6288888888888', 'in', 'halo', 30);
        $this->message('6288888888888', 'out', 'Baik, saya bantu ya.', 25);

        $this->sweep();

        $this->assertNull($this->closingFor('6288888888888'));
    }

    public function test_an_unanswered_question_gets_the_pick_up_where_we_left_off_wording(): void
    {
        $this->message('6289999999999', 'in', 'mau booking', 30);
        $this->message('6289999999999', 'out', 'Jam berapa mau datang?', 25);

        $this->sweep();

        $this->assertStringContainsString('belum sempat dijawab', $this->closingFor('6289999999999')->content);
    }

    public function test_a_registered_patient_is_greeted_by_their_first_name(): void
    {
        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Siti Nurhaliza',
            'whatsapp' => '6281212121212',
        ]);

        $this->message('6281212121212', 'in', 'halo', 30);
        $this->message('6281212121212', 'out', 'Baik, saya bantu ya.', 25);

        $this->sweep();

        $closing = $this->closingFor('6281212121212')->content;

        $this->assertStringContainsString('Halo Siti,', $closing);
        $this->assertStringNotContainsString('Nurhaliza', $closing);
    }

    public function test_the_clinic_own_wording_wins_over_the_default(): void
    {
        ChatbotSetting::query()->update([
            'closing_message' => 'Sampai jumpa :name, salam dari :clinic.',
        ]);

        $this->message('6281313131313', 'in', 'halo', 30);
        $this->message('6281313131313', 'out', 'Baik, saya bantu ya.', 25);

        $this->sweep();

        $this->assertSame(
            'Sampai jumpa Kak, salam dari '.$this->tenant->name.'.',
            $this->closingFor('6281313131313')->content,
        );
    }
}
