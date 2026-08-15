<?php

namespace App\Actions\Commission;

use App\Actions\LogAuditAction;
use App\Models\CommissionRule;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan aturan fee, baik baru maupun perubahan. Digabung karena bentuk
 * datanya identik dan bedanya hanya ada tidaknya baris lama.
 */
class SaveCommissionRuleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?CommissionRule $rule = null): CommissionRule
    {
        if ($rule === null) {
            $rule = CommissionRule::create($data);
            $rule->refresh();

            app(LogAuditAction::class)->handle(
                'commission_rule.created',
                $rule,
                Auth::user(),
                ['attributes' => $rule->getAttributes()],
                'Menambah aturan fee '.$rule->name.'.',
            );

            return $rule;
        }

        $old = $rule->only(array_keys($data));

        $rule->update($data);

        app(LogAuditAction::class)->handle(
            'commission_rule.updated',
            $rule,
            Auth::user(),
            ['old' => $old, 'new' => $rule->only(array_keys($data))],
            'Memperbarui aturan fee '.$rule->name.'.',
        );

        return $rule;
    }
}
