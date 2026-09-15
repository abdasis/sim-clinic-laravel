<?php

namespace App\Actions\Membership;

use App\Actions\LogAuditAction;
use App\Models\MembershipTier;
use Illuminate\Support\Facades\Auth;

class CreateMembershipTierAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): MembershipTier
    {
        $tier = MembershipTier::create($data);

        app(LogAuditAction::class)->handle(
            'membership.tier.created',
            $tier,
            Auth::user(),
            ['attributes' => $tier->getAttributes()],
            'Menambahkan tingkat member '.$tier->name.' dengan potongan '
                .$tier->discount_value.($tier->discount_type->value === 'percent' ? '%' : ' rupiah').'.',
        );

        return $tier;
    }
}
