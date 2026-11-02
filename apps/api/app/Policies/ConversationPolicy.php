<?php

namespace App\Policies;

use App\Models\User;

/**
 * Kotak masuk WhatsApp: membaca utas dan membalas. Admin dan kasir memegang
 * keduanya; dokter dan terapis hanya membaca.
 */
class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inbox.view');
    }

    public function view(User $user): bool
    {
        return $user->can('inbox.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inbox.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('inbox.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('inbox.manage');
    }
}
