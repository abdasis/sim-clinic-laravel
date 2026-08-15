<?php

namespace Tests\Feature\Profile;

use App\Enums\ClinicRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AppearanceApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function meUrl(): string
    {
        return '/api/'.$this->tenant->slug.'/me';
    }

    public function test_preferences_are_saved_and_returned(): void
    {
        $user = $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->patchJson($this->meUrl(), [
            'accent' => 'gold',
            'mode' => 'dark',
            'sidebar_variant' => 'floating',
        ])->assertOk()->assertJsonPath('data.appearance.accent', 'gold');

        $this->assertSame('floating', $user->refresh()->appearance['sidebar_variant']);

        $this->getJson($this->meUrl())
            ->assertOk()
            ->assertJsonPath('data.appearance.mode', 'dark');
    }

    public function test_unset_preferences_come_back_as_null(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->meUrl())
            ->assertOk()
            ->assertJsonPath('data.appearance', null);
    }

    public function test_unknown_values_are_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->patchJson($this->meUrl(), [
            'accent' => 'chartreuse',
            'mode' => 'dark',
            'sidebar_variant' => 'floating',
        ])->assertStatus(422)->assertJsonValidationErrors('accent');

        $this->patchJson($this->meUrl(), [
            'accent' => 'blue',
            'mode' => 'sepia',
            'sidebar_variant' => 'floating',
        ])->assertStatus(422)->assertJsonValidationErrors('mode');
    }

    public function test_guest_cannot_touch_preferences(): void
    {
        $this->tenant = $this->createTenant();

        $this->getJson($this->meUrl())->assertUnauthorized();
    }

    public function test_one_user_cannot_read_another_users_preferences(): void
    {
        // Endpoint-nya selalu memakai user yang login; tidak ada id di URL yang
        // bisa ditukar, dan test ini menjaga bentuk itu tetap begitu.
        $this->actingAsClinicUser();
        $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $other->update(['appearance' => ['accent' => 'red', 'mode' => 'dark', 'sidebar_variant' => 'inset']]);

        $this->getJson($this->meUrl())
            ->assertOk()
            ->assertJsonPath('data.appearance', null);
    }
}
