<?php

namespace App\Actions\Commission;

use App\Actions\LogAuditAction;
use App\Models\CommissionSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan peran mana yang berhak menerima fee dan komisi.
 */
class SaveCommissionSettingAction
{
    /**
     * @param  array<int, string>  $roles
     */
    public function handle(array $roles): CommissionSetting
    {
        $setting = CommissionSetting::query()->firstOrNew([]);
        $old = $setting->eligible_roles ?? CommissionSetting::DEFAULT_ROLES;

        $setting->eligible_roles = array_values(array_unique($roles));
        $setting->save();

        app(LogAuditAction::class)->handle(
            'commission_setting.updated',
            $setting,
            Auth::user(),
            ['old' => ['eligible_roles' => $old], 'new' => ['eligible_roles' => $setting->eligible_roles]],
            'Mengubah peran yang berhak menerima fee dan komisi menjadi '
                .implode(', ', $setting->eligible_roles).'.',
        );

        return $setting;
    }
}
