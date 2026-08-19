<?php

namespace App\Policies;

use App\Models\User;

/**
 * Chatbot menjawab pasien atas nama klinik, jadi yang boleh menyalakan dan
 * mengatur suaranya hanya admin klinik — sama seperti isi halaman publiknya.
 */
class ChatbotSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.view');
    }

    public function update(User $user): bool
    {
        return $user->can('content.manage');
    }
}
