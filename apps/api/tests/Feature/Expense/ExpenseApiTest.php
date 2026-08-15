<?php

namespace Tests\Feature\Expense;

use App\Enums\ClinicRole;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        // Operan kiri menang pada `+`, jadi override harus di kiri.
        return $overrides + [
            'spent_at' => '2026-05-31',
            'category' => 'operational',
            'description' => 'Pengeluaran klinik',
            'amount' => 3_800_000,
        ];
    }

    public function test_records_expense_with_recorder(): void
    {
        $user = $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('expenses'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.category_label', 'Operasional Klinik')
            ->assertJsonPath('data.recorded_by_name', $user->name);
    }

    public function test_rejects_zero_amount(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('expenses'), $this->payload(['amount' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_summary_groups_by_category(): void
    {
        $this->actingAsClinicUser();

        // Tiga baris "Rincian Pengeluaran" dari laporan bulanan klinik.
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-05-10', 'category' => 'operational', 'amount' => 3_800_000]);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-05-20', 'category' => 'salary', 'amount' => 1_000_000]);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-05-25', 'category' => 'incentive', 'amount' => 500_000]);
        // Di luar periode, tidak boleh ikut terhitung.
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-06-02', 'category' => 'salary', 'amount' => 9_000_000]);

        $this->getJson($this->tenantUrl('expenses/summary?from=2026-05-01&to=2026-05-31'))
            ->assertOk()
            ->assertJsonPath('data.total', 5_300_000)
            ->assertJsonPath('data.entries', 3)
            ->assertJsonPath('data.by_category.0.category', 'operational')
            ->assertJsonPath('data.by_category.0.total', 3_800_000);
    }

    public function test_filters_by_period_and_category(): void
    {
        $this->actingAsClinicUser();

        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-05-10', 'category' => 'salary']);
        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-05-11', 'category' => 'rent']);

        $this->getJson($this->tenantUrl('expenses?filter[category]=rent'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson($this->tenantUrl('expenses?filter[from]=2026-05-11&filter[to]=2026-05-11'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_soft_deletes_keep_history(): void
    {
        $this->actingAsClinicUser();
        $expense = Expense::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->deleteJson($this->tenantUrl('expenses/'.$expense->id))->assertOk();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_cashier_cannot_see_expenses(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('expenses'))->assertForbidden();
    }
}
