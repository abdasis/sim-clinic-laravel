<?php

namespace Tests\Feature\Broadcast;

use App\Enums\BroadcastAudience;
use App\Models\Broadcast;
use App\Models\Patient;
use App\Support\BroadcastAudienceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Broadcast ke sekumpulan kontak yang dipilih sendiri.
 *
 * Sasaran yang ada sebelumnya semuanya berupa aturan — semua pasien, yang
 * lama tidak datang, yang pernah ambil layanan tertentu. Tidak ada jalan
 * untuk mengirim ke belasan orang tertentu saja, padahal itu yang paling
 * sering dibutuhkan: menyapa peserta promo, atau mengabari sekelompok kecil
 * pasien yang jadwalnya bergeser.
 */
class SelectedContactsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
    }

    private function makePatient(string $name, string $phone = '081234567890', bool $optIn = true): Patient
    {
        return Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'whatsapp' => $phone,
            'whatsapp_opt_in' => $optIn,
        ]);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<string, mixed>
     */
    private function preview(array $ids): array
    {
        return $this->getJson(
            $this->tenantUrl('broadcasts/audience-preview').'?'.http_build_query([
                'audience' => 'selected',
                'patient_ids' => $ids,
            ]),
        )->assertOk()->json('data');
    }

    /** Inti permintaannya: hanya yang dipilih yang jadi penerima. */
    public function test_only_the_chosen_contacts_become_recipients(): void
    {
        $ani = $this->makePatient('Ani', '081100000001');
        $this->makePatient('Budi', '081100000002');
        $citra = $this->makePatient('Citra', '081100000003');

        $data = $this->preview([$ani->id, $citra->id]);

        $this->assertSame(2, $data['count']);
        $this->assertSame(
            ['Ani', 'Citra'],
            collect($data['recipients'])->pluck('name')->all(),
        );
    }

    /** Tanpa satu pun kontak, tidak ada yang jadi penerima. */
    public function test_no_chosen_contact_means_no_recipient(): void
    {
        $this->makePatient('Ani');

        $built = app(BroadcastAudienceBuilder::class)
            ->build(BroadcastAudience::Selected, ['patient_ids' => []]);

        $this->assertSame(0, $built['recipients']->count());
    }

    /**
     * Penjagaan terpenting: daftar kosong tidak boleh berarti "semua pasien".
     * Broadcast promo yang tidak sengaja terkirim ke seluruh basis pasien
     * tidak bisa ditarik kembali.
     */
    public function test_an_empty_selection_never_means_everyone(): void
    {
        $this->makePatient('Ani');
        $this->makePatient('Budi');

        $built = app(BroadcastAudienceBuilder::class)
            ->build(BroadcastAudience::Selected, []);

        $this->assertSame(0, $built['recipients']->count());
    }

    /** Yang menolak promosi tetap tidak dikirimi walau dipilih tangan. */
    public function test_a_chosen_contact_who_opted_out_is_excluded(): void
    {
        $ani = $this->makePatient('Ani', '081100000001');
        $budi = $this->makePatient('Budi', '081100000002', optIn: false);

        $data = $this->preview([$ani->id, $budi->id]);

        $this->assertSame(1, $data['count']);
        $this->assertSame(['Ani'], collect($data['recipients'])->pluck('name')->all());
    }

    /**
     * Dan hilangnya diberi keterangan, bukan dibiarkan senyap: kontak yang
     * dipilih tangan lalu lenyap tanpa alasan terbaca sebagai sistem rusak.
     */
    public function test_the_opted_out_count_is_reported(): void
    {
        $ani = $this->makePatient('Ani', '081100000001');
        $budi = $this->makePatient('Budi', '081100000002', optIn: false);

        $this->assertSame(1, $this->preview([$ani->id, $budi->id])['opted_out']);
    }

    /** Nomor yang tidak terbaca dilaporkan terpisah dari yang menolak. */
    public function test_an_unreadable_number_is_reported_separately(): void
    {
        $ani = $this->makePatient('Ani', '081100000001');
        $budi = $this->makePatient('Budi', '-');

        $data = $this->preview([$ani->id, $budi->id]);

        $this->assertSame(1, $data['count']);
        $this->assertSame(1, $data['without_phone']);
        $this->assertSame(0, $data['opted_out']);
    }

    /** Broadcast benar-benar tersimpan dengan penerima yang dipilih itu. */
    public function test_a_broadcast_is_created_for_the_chosen_contacts(): void
    {
        $ani = $this->makePatient('Ani', '081100000001');
        $this->makePatient('Budi', '081100000002');

        $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo Agustus',
            'message' => 'Halo {nama}',
            'audience' => 'selected',
            'audience_params' => ['patient_ids' => [$ani->id]],
        ])->assertCreated();

        $broadcast = Broadcast::query()->latest('id')->first();

        $this->assertSame(1, $broadcast->recipients()->count());
        $this->assertSame('Ani', $broadcast->recipients()->first()->name);
    }

    /** Menyimpan tanpa memilih kontak ditolak sebagai galat formulir. */
    public function test_creating_without_any_contact_is_rejected(): void
    {
        $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo Agustus',
            'message' => 'Halo {nama}',
            'audience' => 'selected',
            'audience_params' => ['patient_ids' => []],
        ])->assertStatus(422)->assertJsonValidationErrors('audience_params.patient_ids');
    }

    /** Pasien klinik lain tidak bisa dijadikan penerima. */
    public function test_a_patient_from_another_clinic_is_rejected(): void
    {
        $other = $this->createTenant('klinik-lain');
        $foreign = Patient::factory()->create(['tenant_id' => $other->id]);

        app()->instance('tenant', $this->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo Agustus',
            'message' => 'Halo {nama}',
            'audience' => 'selected',
            'audience_params' => ['patient_ids' => [$foreign->id]],
        ])->assertStatus(422);
    }

    /**
     * Seluruh nama ikut dikirim, bukan tiga contoh.
     *
     * Pratinjau dulu memotongnya di tiga baris — dan layarnya bahkan tidak
     * menampilkannya sama sekali, jadi admin menekan kirim ke ratusan orang
     * tanpa pernah melihat satu pun nama yang akan menerimanya. Salah pilih
     * sasaran baru ketahuan setelah pesannya terlanjur berangkat.
     */
    public function test_the_preview_lists_every_recipient_not_just_three(): void
    {
        $ids = [];

        foreach (range(1, 8) as $index) {
            $ids[] = $this->makePatient(
                'Pasien '.$index,
                '08110000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            )->id;
        }

        $data = $this->preview($ids);

        $this->assertSame(8, $data['count']);
        $this->assertCount(8, $data['recipients']);
    }

    /** Berlaku juga untuk sasaran semua pasien, bukan hanya kontak terpilih. */
    public function test_the_full_list_also_applies_to_the_broad_audiences(): void
    {
        foreach (range(1, 5) as $index) {
            $this->makePatient(
                'Pasien '.$index,
                '08110000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            );
        }

        $data = $this->getJson(
            $this->tenantUrl('broadcasts/audience-preview').'?audience=all',
        )->assertOk()->json('data');

        $this->assertCount(5, $data['recipients']);
    }

    /** Tiap baris membawa nama dan nomornya, supaya bisa diperiksa sebelum kirim. */
    public function test_each_row_carries_a_name_and_a_number(): void
    {
        $ani = $this->makePatient('Ani Lestari', '081234567890');

        $row = $this->preview([$ani->id])['recipients'][0];

        $this->assertSame('Ani Lestari', $row['name']);
        $this->assertSame('6281234567890', $row['phone']);
        $this->assertSame($ani->id, $row['patient_id']);
    }
}
