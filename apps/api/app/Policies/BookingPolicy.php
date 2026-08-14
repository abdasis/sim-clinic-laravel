<?php

namespace App\Policies;

use App\Models\User;

/**
 * Booking: semua peran klinik (admin/dokter/terapis/kasir) = view + write (R2 matriks).
 */
class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('booking.view');
    }

    public function view(User $user): bool
    {
        return $user->can('booking.view');
    }

    public function create(User $user): bool
    {
        return $user->can('booking.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('booking.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('booking.manage');
    }
}
