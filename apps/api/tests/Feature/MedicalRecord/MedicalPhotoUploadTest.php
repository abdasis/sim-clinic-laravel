<?php

namespace Tests\Feature\MedicalRecord;

use App\Enums\ClinicRole;
use App\Enums\MedicalPhotoType;
use App\Models\Activity;
use App\Models\MedicalPhoto;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Unggah foto sebelum/sesudah ke rekam medis.
 *
 * Foto pasien adalah data paling sensitif di sistem ini, jadi yang dijaga
 * bukan cuma berhasilnya: berkasnya tersimpan di folder milik kliniknya
 * sendiri, jenis berkas selain gambar ditolak, dan rekam medis klinik lain
 * tidak bisa dititipi foto lewat id yang ditebak.
 */
class MedicalPhotoUploadTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function makeRecord(): MedicalRecord
    {
        return MedicalRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'author_id' => auth()->id(),
        ]);
    }

    private function photoUrl(MedicalRecord $record): string
    {
        return $this->tenantUrl("medical-records/{$record->id}/photos");
    }

    public function test_doctor_uploads_a_before_photo(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();

        $response = $this->postJson($this->photoUrl($record), [
            'file' => UploadedFile::fake()->image('sebelum.jpg'),
            'type' => 'before',
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'before');

        $photo = MedicalPhoto::first();
        $this->assertNotNull($photo);
        $this->assertSame(MedicalPhotoType::Before, $photo->type);
        $this->assertSame($record->id, $photo->medical_record_id);
        Storage::disk('public')->assertExists($photo->path);
    }

    /**
     * Berkasnya tersimpan di folder per klinik per rekam medis — pemisahan
     * ini yang membuat foto satu klinik tidak pernah tercampur dengan lain.
     */
    public function test_the_file_lands_in_a_folder_scoped_to_clinic_and_record(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();

        $this->postJson($this->photoUrl($record), [
            'file' => UploadedFile::fake()->image('sesudah.png'),
            'type' => 'after',
        ])->assertCreated();

        $this->assertStringStartsWith(
            "medical-photos/{$this->tenant->id}/{$record->id}/",
            MedicalPhoto::first()->path,
        );
    }

    public function test_upload_is_recorded_in_the_audit_log(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();

        $this->postJson($this->photoUrl($record), [
            'file' => UploadedFile::fake()->image('sebelum.jpg'),
            'type' => 'before',
        ])->assertCreated();

        $activity = Activity::where('event', 'medical_record.photo_uploaded')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('#'.$record->id, $activity->description);
        $this->assertSame('sebelum.jpg', $activity->properties['original_name']);
    }

    /** Hanya gambar; dokumen dan berkas lain ditolak sebagai galat formulir. */
    public function test_a_non_image_file_is_rejected(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();

        $this->postJson($this->photoUrl($record), [
            'file' => UploadedFile::fake()->create('hasil-lab.pdf', 100, 'application/pdf'),
            'type' => 'before',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, MedicalPhoto::query()->count());
    }

    /** Batasnya 2 MB — kamera ponsel gampang melewatinya kalau tidak dijaga. */
    public function test_an_oversized_image_is_rejected(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();

        $this->postJson($this->photoUrl($record), [
            'file' => UploadedFile::fake()->image('besar.jpg')->size(3000),
            'type' => 'before',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_an_unknown_photo_type_is_rejected(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();

        $this->postJson($this->photoUrl($record), [
            'file' => UploadedFile::fake()->image('sebelum.jpg'),
            'type' => 'selama',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    /** Terapis ikut mengerjakan treatment, jadi ia juga mengunggah fotonya. */
    public function test_therapist_may_upload_a_photo(): void
    {
        $this->actingAsClinicUser(ClinicRole::Therapist);
        $record = $this->makeRecord();

        $this->postJson($this->photoUrl($record), [
            'file' => UploadedFile::fake()->image('sesudah.jpg'),
            'type' => 'after',
        ])->assertCreated();
    }

    /** Kasir tidak pernah menyentuh rekam medis. */
    public function test_cashier_cannot_upload_a_photo(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $record = $this->makeRecord();

        $this->postJson($this->photoUrl($record), [
            'file' => UploadedFile::fake()->image('sebelum.jpg'),
            'type' => 'before',
        ])->assertForbidden();

        $this->assertSame(0, MedicalPhoto::query()->count());
    }

    /** Rekam medis klinik lain tidak bisa dititipi foto lewat id yang ditebak. */
    public function test_a_record_from_another_clinic_is_not_found(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $other = $this->createTenant('klinik-lain');
        $foreign = MedicalRecord::factory()->create([
            'tenant_id' => $other->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $other->id])->id,
        ]);

        // createTenant memindahkan tenant aktif; kembalikan ke tenant penguji.
        app()->instance('tenant', $this->tenant);

        $this->postJson($this->photoUrl($foreign), [
            'file' => UploadedFile::fake()->image('sebelum.jpg'),
            'type' => 'before',
        ])->assertNotFound();

        $this->assertSame(0, MedicalPhoto::query()->count());
    }
}
