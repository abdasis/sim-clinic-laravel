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
        return $user->can('clinic.access', ['booking', 'r']);
    }

    public function view(User $user): bool
    {
        return $user->can('clinic.access', ['booking', 'r']);
    }

    public function create(User $user): bool
    {
        return $user->can('clinic.access', ['booking', 'w']);
    }

    public function update(User $user): bool
    {
        return $user->can('clinic.access', ['booking', 'w']);
    }

    public function delete(User $user): bool
    {
        return $user->can('clinic.access', ['booking', 'w']);
    }
}
