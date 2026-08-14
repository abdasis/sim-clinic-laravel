<?php

namespace Tests\Feature\CompanyProfile;

use App\Enums\ClinicRole;
use App\Models\Activity;
use App\Models\CompanyProfileSetting;
use App\Models\CompanyProfileSlide;
use App\Models\CompanyTreatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * CMS company profile hanya untuk admin, dan tiap perubahan harus
 * meninggalkan jejak yang bisa dibaca manusia.
 */
class CompanyContentCmsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_admin_can_add_a_slide(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $response = $this->postJson($this->cmsUrl('slides'), [
            'title' => ['id' => 'Kulit sehat', 'en' => 'Healthy skin'],
            'subtitle' => ['id' => 'Mulai hari ini', 'en' => 'Start today'],
            'image_path' => 'company-profile/1/slides/hero.jpg',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title.id', 'Kulit sehat');

        $this->assertDatabaseHas('company_profile_slides', [
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_indonesian_version_is_required(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->postJson($this->cmsUrl('slides'), [
            'title' => ['en' => 'English only'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title.id');
    }

    public function test_non_admin_roles_are_locked_out(): void
    {
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->getJson($this->cmsUrl('slides'))->assertForbidden();
        $this->postJson($this->cmsUrl('slides'), [
            'title' => ['id' => 'Coba'],
        ])->assertForbidden();
    }

    public function test_unknown_entity_is_not_found(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->getJson($this->cmsUrl('users'))->assertNotFound();
    }

    public function test_treatment_slug_is_unique_per_tenant(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        CompanyTreatment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'facial-glow',
        ]);

        $this->postJson($this->cmsUrl('treatments'), [
            'slug' => 'facial-glow',
            'title' => ['id' => 'Facial Glow'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_same_slug_is_fine_in_another_tenant(): void
    {
        $other = $this->createTenant('klinik-lain');
        CompanyTreatment::factory()->create([
            'tenant_id' => $other->id,
            'slug' => 'facial-glow',
        ]);

        $this->tenant = $this->createTenant('klinik-uji');
        $this->actingAsClinicUser(ClinicRole::Admin, $this->tenant);

        $this->postJson($this->cmsUrl('treatments'), [
            'slug' => 'facial-glow',
            'title' => ['id' => 'Facial Glow'],
        ])->assertCreated();
    }

    public function test_toggling_hides_a_row_from_the_landing(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        CompanyProfileSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $slide = CompanyProfileSlide::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->cmsUrl('slides/'.$slide->id.'/toggle'))
            ->assertOk();

        $this->assertFalse((bool) $slide->fresh()->is_active);

        $this->getJson('/api/'.$this->tenant->slug.'/profile')
            ->assertJsonCount(0, 'data.slides');
    }

    public function test_reorder_rewrites_sort_order(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $first = CompanyProfileSlide::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sort_order' => 1,
        ]);
        $second = CompanyProfileSlide::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sort_order' => 2,
        ]);

        $this->postJson($this->cmsUrl('slides/reorder'), [
            'ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_reorder_cannot_touch_another_tenants_rows(): void
    {
        $other = $this->createTenant('klinik-lain');
        $foreign = CompanyProfileSlide::factory()->create([
            'tenant_id' => $other->id,
            'sort_order' => 9,
        ]);

        $this->tenant = $this->createTenant('klinik-uji');
        $this->actingAsClinicUser(ClinicRole::Admin, $this->tenant);

        $this->postJson($this->cmsUrl('slides/reorder'), [
            'ids' => [$foreign->id],
        ])->assertOk();

        $this->assertSame(9, $foreign->fresh()->sort_order);
    }

    public function test_publishing_is_recorded_narratively(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->postJson($this->cmsUrl('settings/publish'))
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $activity = Activity::query()->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('company_settings.updated', $activity->event);
        $this->assertStringContainsString('Menerbitkan halaman', $activity->description);
    }

    public function test_update_records_old_and_new_values(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $slide = CompanyProfileSlide::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => ['id' => 'Judul lama'],
        ]);

        $this->putJson($this->cmsUrl('slides/'.$slide->id), [
            'title' => ['id' => 'Judul baru'],
        ])->assertOk();

        $activity = Activity::query()->where('event', 'company_content.updated')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Judul lama', json_encode($activity->properties['old']));
        $this->assertStringContainsString('Judul baru', json_encode($activity->properties['new']));
    }

    public function test_settings_are_created_on_first_save(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->putJson($this->cmsUrl('settings'), [
            'site_name' => ['id' => 'Klinik Uji', 'en' => 'Test Clinic'],
            'default_locale' => 'id',
        ])
            ->assertOk()
            ->assertJsonPath('data.site_name.en', 'Test Clinic');

        $this->assertDatabaseCount('company_profile_settings', 1);
    }

    public function test_media_upload_is_stored_under_the_tenant(): void
    {
        Storage::fake('public');
        $this->actingAsClinicUser(ClinicRole::Admin);

        $response = $this->postJson($this->cmsUrl('media'), [
            'file' => UploadedFile::fake()->image('hero.jpg'),
            'entity' => 'slides',
        ]);

        $response->assertCreated();

        $path = $response->json('data.path');

        $this->assertStringStartsWith("company-profile/{$this->tenant->id}/slides/", $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_content_of_another_tenant_is_not_reachable(): void
    {
        $other = $this->createTenant('klinik-lain');
        $foreign = CompanyProfileSlide::factory()->create(['tenant_id' => $other->id]);

        $this->tenant = $this->createTenant('klinik-uji');
        $this->actingAsClinicUser(ClinicRole::Admin, $this->tenant);

        $this->getJson($this->cmsUrl('slides/'.$foreign->id))->assertNotFound();
        $this->deleteJson($this->cmsUrl('slides/'.$foreign->id))->assertNotFound();

        $this->assertDatabaseHas('company_profile_slides', ['id' => $foreign->id]);
    }

    private function cmsUrl(string $path): string
    {
        return '/api/'.$this->tenant->slug.'/clinic/company-profile/'.$path;
    }
}
