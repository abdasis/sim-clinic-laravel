<?php

namespace App\Services;

use App\Actions\Membership\CreateMembershipTierAction;
use App\Actions\Membership\DeleteMembershipTierAction;
use App\Actions\Membership\UpdateMembershipTierAction;
use App\Models\MembershipTier;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MembershipTier
    {
        return DB::transaction(fn () => app(CreateMembershipTierAction::class)->handle($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MembershipTier $tier, array $data): MembershipTier
    {
        return DB::transaction(fn () => app(UpdateMembershipTierAction::class)->handle($tier, $data));
    }

    public function delete(MembershipTier $tier): void
    {
        DB::transaction(fn () => app(DeleteMembershipTierAction::class)->handle($tier));
    }
}
