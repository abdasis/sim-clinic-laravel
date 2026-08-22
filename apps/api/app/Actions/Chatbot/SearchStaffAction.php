<?php

namespace App\Actions\Chatbot;

use App\Enums\ClinicRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staf yang boleh disebut ke pasien dan boleh dipasang di booking: dokter dan
 * terapis saja. Kasir dan admin tidak mengerjakan treatment, jadi menyebutnya
 * hanya membuat pasien meminta jadwal ke orang yang salah.
 */
class SearchStaffAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(?string $keyword = null): array
    {
        return User::query()
            ->whereIn('clinic_role', [ClinicRole::Doctor, ClinicRole::Therapist])
            ->where('status', UserStatus::Active)
            ->tap(fn (Builder $query) => Search::apply($query, 'name', $keyword))
            ->orderBy('name')
            ->get(['id', 'name', 'clinic_role'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->clinic_role?->label(),
            ])
            ->all();
    }
}
