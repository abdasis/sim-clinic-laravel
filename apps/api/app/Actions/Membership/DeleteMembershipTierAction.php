<?php

namespace App\Actions\Membership;

use App\Actions\LogAuditAction;
use App\Models\MembershipTier;
use Illuminate\Support\Facades\Auth;

/**
 * Hapus tingkat member yang memang tidak dipegang siapa pun.
 *
 * Tingkat yang masih menempel di pasien ditolak, bukan dilepas diam-diam:
 * melepasnya berarti potongan yang sedang berjalan hilang tanpa ada yang
 * memutuskan, dan pasien baru tahu saat membayar di kasir.
 */
class DeleteMembershipTierAction
{
    public function handle(MembershipTier $tier): void
    {
        $holders = $tier->patients()->count();

        abort_if($holders > 0, 422, __('membership.in_use', ['count' => $holders]));

        $snapshot = $tier->getAttributes();
        $name = $tier->name;

        $tier->delete();

        app(LogAuditAction::class)->handle(
            'membership.tier.deleted',
            $tier,
            Auth::user(),
            ['old' => $snapshot],
            'Menghapus tingkat member '.$name.'.',
        );
    }
}
