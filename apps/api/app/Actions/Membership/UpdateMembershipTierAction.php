<?php

namespace App\Actions\Membership;

use App\Actions\LogAuditAction;
use App\Models\MembershipTier;
use Illuminate\Support\Facades\Auth;

class UpdateMembershipTierAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(MembershipTier $tier, array $data): MembershipTier
    {
        $old = $tier->getOriginal();

        $tier->update($data);

        app(LogAuditAction::class)->handle(
            'membership.tier.updated',
            $tier,
            Auth::user(),
            ['old' => $old, 'new' => $tier->getAttributes()],
            'Memperbarui tingkat member '.$tier->name.'.',
        );

        return $tier->refresh();
    }
}
