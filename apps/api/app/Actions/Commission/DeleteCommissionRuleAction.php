<?php

namespace App\Actions\Commission;

use App\Actions\LogAuditAction;
use App\Models\CommissionRule;
use Illuminate\Support\Facades\Auth;

/**
 * Hentikan aturan fee.
 *
 * Barisnya tidak benar-benar dibuang: ia ditutup pada saat penghapusan.
 * Fee bulan-bulan lalu dihitung dari tarif yang berlaku saat itu, jadi
 * menghapus barisnya akan membuat periode yang sudah lewat kehilangan
 * dasar perhitungannya dan menghasilkan angka berbeda bila dihitung ulang.
 */
class DeleteCommissionRuleAction
{
    public function handle(CommissionRule $rule): void
    {
        $snapshot = $rule->getAttributes();
        $name = $rule->name;
        $at = now();

        $rule->forceFill(['effective_to' => $at, 'is_active' => false])->save();

        app(LogAuditAction::class)->handle(
            'commission_rule.deleted',
            $rule,
            Auth::user(),
            ['attributes' => $snapshot, 'effective_to' => $at->toDateTimeString()],
            'Menghentikan aturan fee '.$name.' terhitung '
                .$at->translatedFormat('d F Y H:i').'.',
        );
    }
}
