<?php

namespace App\Actions\Loyalty;

use App\Actions\LogAuditAction;
use App\Models\LoyaltySetting;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan tarif poin klinik ini.
 *
 * Perubahannya dicatat lengkap dengan nilai lama: tarif poin menentukan
 * berapa yang klinik berikan cuma-cuma, dan pertanyaan "sejak kapan jadi
 * segini" harus bisa dijawab tanpa menebak.
 */
class SaveLoyaltySettingAction
{
    /**
     * @param  array{earn_rate: float|string, redeem_rate: float|string, min_redeem: int}  $data
     */
    public function handle(array $data): LoyaltySetting
    {
        $setting = LoyaltySetting::query()->firstOrNew([]);
        $old = $setting->exists
            ? $setting->only(['earn_rate', 'redeem_rate', 'min_redeem'])
            : LoyaltySetting::current();

        $setting->fill($data)->save();

        app(LogAuditAction::class)->handle(
            'loyalty.setting.updated',
            $setting,
            Auth::user(),
            ['old' => $old, 'new' => $setting->only(['earn_rate', 'redeem_rate', 'min_redeem'])],
            'Mengubah tarif poin loyalitas: tiap Rp'.number_format((float) $setting->earn_rate, 0, ',', '.')
                .' belanja dapat 1 poin, dan 1 poin memotong Rp'
                .number_format((float) $setting->redeem_rate, 0, ',', '.')
                .' (tukar paling sedikit '.$setting->min_redeem.' poin).',
        );

        return $setting;
    }
}
