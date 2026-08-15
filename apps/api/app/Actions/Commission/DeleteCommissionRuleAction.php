<?php

namespace App\Actions\Commission;

use App\Actions\LogAuditAction;
use App\Models\CommissionRule;
use Illuminate\Support\Facades\Auth;

class DeleteCommissionRuleAction
{
    public function handle(CommissionRule $rule): void
    {
        $snapshot = $rule->getAttributes();
        $name = $rule->name;

        $rule->delete();

        app(LogAuditAction::class)->handle(
            'commission_rule.deleted',
            $rule,
            Auth::user(),
            ['attributes' => $snapshot],
            'Menghapus aturan fee '.$name.'.',
        );
    }
}
