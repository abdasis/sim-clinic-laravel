<?php

namespace Tests\Feature\MedicalRecord;

use App\Enums\ClinicRole;
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
 * Hapus foto sebelum/sesudah dari rekam medis.
 *
 * Foto yang salah unggah harus bisa dicabut — tanpa itu, satu jepretan
 * keliru menempel selamanya di berkas pasien. Yang dijaga di sini: berkasnya
 * benar-benar hilang dari disk, foto milik rekam medis lain tidak bisa ikut
 * terhapus lewat id yang ditebak, dan kasir tetap tidak boleh menyentuhnya.
 */
class MedicalPhotoDeleteTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function makeRecord(?int $tenantId = null): MedicalRecord
    {
        $tenantId ??= $this->tenant->id;

        return MedicalRecord::factory()->create([
            'tenant_id' => $tenantId,
            'patient_id' => Patient::factory()->create(['tenant_id' => $tenantId])->id,
            'author_id' => auth()->id(),
        ]);
    }

    /** Unggah lewat endpoint aslinya, supaya berkasnya benar-benar ada di disk. */
    private function uploadPhoto(MedicalRecord $record, string $type = 'before'): MedicalPhoto
    {
        $this->postJson($this->tenantUrl("medical-records/{$record->id}/photos"), [
            'file' => UploadedFile::fake()->image($type.'.jpg'),
            'type' => $type,
        ])->assertCreated();

        return MedicalPhoto::latest('id')->first();
    }

    private function deleteUrl(MedicalRecord $record, MedicalPhoto|int $photo): string
    {
        $id = $photo instanceof MedicalPhoto ? $photo->id : $photo;

        return $this->tenantUrl("medical-records/{$record->id}/photos/{$id}");
    }

    public function test_doctor_deletes_a_photo_and_the_file_is_gone(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();
        $photo = $this->uploadPhoto($record);

        Storage::disk('public')->assertExists($photo->path);

        $this->deleteJson($this->deleteUrl($record, $photo))
            ->assertOk()
            ->assertJsonPath('data.id', $photo->id);

        $this->assertSame(0, MedicalPhoto::query()->count());
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_deleting_a_photo_leaves_the_others_alone(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();
        $before = $this->uploadPhoto($record, 'before');
        $after = $this->uploadPhoto($record, 'after');

        $this->deleteJson($this->deleteUrl($record, $before))->assertOk();

        $this->assertSame([$after->id], MedicalPhoto::query()->pluck('id')->all());
        Storage::disk('public')->assertExists($after->path);
    }

    public function test_deletion_is_recorded_in_the_audit_log(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();
        $photo = $this->uploadPhoto($record, 'after');

        $this->deleteJson($this->deleteUrl($record, $photo))->assertOk();

        $activity = Activity::where('event', 'medical_record.photo_deleted')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('#'.$record->id, $activity->description);
        $this->assertSame($photo->path, $activity->properties['old']['path']);
    }

    /**
     * Foto milik rekam medis lain tidak ikut terhapus walau id-nya benar:
     * rutenya mengikat foto ke catatan induknya, jadi pasangan yang tidak
     * cocok berhenti sebagai 404.
     */
    public function test_a_photo_belonging_to_another_record_is_not_found(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();
        $otherRecord = $this->makeRecord();
        $photo = $this->uploadPhoto($otherRecord);

        $this->deleteJson($this->deleteUrl($record, $photo))->assertNotFound();

        $this->assertSame(1, MedicalPhoto::query()->count());
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_a_photo_from_another_clinic_is_not_found(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $other = $this->createTenant('klinik-lain');
        $foreignRecord = MedicalRecord::factory()->create([
            'tenant_id' => $other->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $other->id])->id,
        ]);
        $foreignPhoto = MedicalPhoto::factory()->create([
            'tenant_id' => $other->id,
            'medical_record_id' => $foreignRecord->id,
        ]);

        // createTenant memindahkan tenant aktif; kembalikan ke tenant penguji.
        app()->instance('tenant', $this->tenant);

        $this->deleteJson($this->deleteUrl($foreignRecord, $foreignPhoto))->assertNotFound();

        $this->assertSame(1, MedicalPhoto::withoutGlobalScopes()->count());
    }

    public function test_therapist_may_delete_a_photo(): void
    {
        $this->actingAsClinicUser(ClinicRole::Therapist);
        $record = $this->makeRecord();
        $photo = $this->uploadPhoto($record);

        $this->deleteJson($this->deleteUrl($record, $photo))->assertOk();
    }

    public function test_cashier_cannot_delete_a_photo(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);
        $record = $this->makeRecord();
        $photo = $this->uploadPhoto($record);

        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->deleteJson($this->deleteUrl($record, $photo))->assertForbidden();

        $this->assertSame(1, MedicalPhoto::query()->count());
        Storage::disk('public')->assertExists($photo->path);
    }
}
