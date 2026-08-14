<?php

namespace Tests\Feature\CompanyProfile;

use App\Models\CompanyBrand;
use App\Models\CompanyNavigationItem;
use App\Models\CompanyProfileSetting;
use App\Models\CompanyProfileSlide;
use App\Models\CompanyPromo;
use App\Models\CompanyTestimonial;
use App\Models\CompanyTreatment;
use App\Models\CompanyValueProp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Landing company profile dibaca pengunjung tanpa login, jadi yang dijaga di
 * sini: hanya konten milik tenant itu yang keluar, hanya kalau sudah
 * diterbitkan, dan hanya baris yang aktif.
 */
class CompanyProfileLandingTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    public function test_visitor_sees_published_landing_without_logging_in(): void
    {
        CompanyProfileSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyProfileSlide::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyValueProp::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyTreatment::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyPromo::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyBrand::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyTestimonial::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson($this->profileUrl());

        $response->assertOk();
        $response->assertJsonPath('data.is_published', true);
        $response->assertJsonCount(1, 'data.slides');
        $response->assertJsonCount(1, 'data.value_props');
        $response->assertJsonCount(1, 'data.treatments');
        $response->assertJsonCount(1, 'data.promos');
        $response->assertJsonCount(1, 'data.brands');
        $response->assertJsonCount(1, 'data.testimonials');
    }

    public function test_draft_landing_hides_its_content(): void
    {
        CompanyProfileSetting::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
        CompanyProfileSlide::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson($this->profileUrl());

        $response->assertOk();
        $response->assertJsonPath('data.is_published', false);
        $response->assertJsonCount(0, 'data.slides');
    }

    public function test_landing_without_settings_is_treated_as_draft(): void
    {
        $this->getJson($this->profileUrl())
            ->assertOk()
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.settings', null);
    }

    public function test_inactive_rows_are_left_out(): void
    {
        CompanyProfileSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyProfileSlide::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);
        CompanyPromo::factory()->expired()->create(['tenant_id' => $this->tenant->id]);

        $this->getJson($this->profileUrl())
            ->assertOk()
            ->assertJsonCount(0, 'data.slides')
            ->assertJsonCount(0, 'data.promos');
    }

    public function test_navigation_is_split_by_position(): void
    {
        CompanyProfileSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyNavigationItem::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyNavigationItem::factory()->footer()->create(['tenant_id' => $this->tenant->id]);

        $this->getJson($this->profileUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data.navigation.header')
            ->assertJsonCount(1, 'data.navigation.footer');
    }

    public function test_content_is_returned_in_the_requested_language(): void
    {
        CompanyProfileSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'tagline' => ['id' => 'Halo', 'en' => 'Hello'],
        ]);

        $this->getJson($this->profileUrl())
            ->assertJsonPath('data.settings.tagline', 'Halo');

        $this->getJson($this->profileUrl('locale=en'))
            ->assertJsonPath('data.settings.tagline', 'Hello');
    }

    public function test_missing_translation_falls_back_to_indonesian(): void
    {
        CompanyProfileSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'tagline' => ['id' => 'Hanya Indonesia'],
        ]);

        $this->getJson($this->profileUrl('locale=en'))
            ->assertJsonPath('data.settings.tagline', 'Hanya Indonesia');
    }

    public function test_landing_never_shows_another_tenants_content(): void
    {
        CompanyProfileSetting::factory()->create(['tenant_id' => $this->tenant->id]);

        $other = $this->createTenant('klinik-lain');
        CompanyProfileSlide::factory()->create(['tenant_id' => $other->id]);

        app()->instance('tenant', $this->tenant);

        $this->getJson($this->profileUrl())
            ->assertOk()
            ->assertJsonCount(0, 'data.slides');
    }

    public function test_treatment_detail_is_reachable_by_slug(): void
    {
        CompanyProfileSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyTreatment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'facial-glow',
        ]);

        $this->getJson('/api/'.$this->tenant->slug.'/profile/treatments/facial-glow')
            ->assertOk()
            ->assertJsonPath('data.slug', 'facial-glow');
    }

    public function test_treatment_detail_is_hidden_while_landing_is_draft(): void
    {
        CompanyProfileSetting::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
        CompanyTreatment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'facial-glow',
        ]);

        $this->getJson('/api/'.$this->tenant->slug.'/profile/treatments/facial-glow')
            ->assertNotFound();
    }

    private function profileUrl(string $query = ''): string
    {
        return '/api/'.$this->tenant->slug.'/profile'.($query ? '?'.$query : '');
    }
}
