<?php

namespace App\Services;

use App\Actions\Commission\DeleteCommissionRuleAction;
use App\Actions\Commission\SaveCommissionRuleAction;
use App\Models\CommissionRule;
use App\Support\CommissionCalculator;

/**
 * Orkestrasi aturan fee terapis dan perhitungannya.
 */
class CommissionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?CommissionRule $rule = null): CommissionRule
    {
        return app(SaveCommissionRuleAction::class)->handle($data, $rule);
    }

    public function delete(CommissionRule $rule): void
    {
        app(DeleteCommissionRuleAction::class)->handle($rule);
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(string $from, string $to): array
    {
        return (new CommissionCalculator($from, $to))->run();
    }
}
