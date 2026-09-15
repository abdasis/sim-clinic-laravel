<?php

namespace App\Services;

use App\Actions\Loyalty\SaveLoyaltySettingAction;
use App\Models\LoyaltySetting;

class LoyaltyService
{
    /**
     * @param  array{earn_rate: float|string, redeem_rate: float|string, min_redeem: int}  $data
     */
    public function saveSetting(array $data): LoyaltySetting
    {
        return app(SaveLoyaltySettingAction::class)->handle($data);
    }
}
