<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastRecipientStatus;
use App\Models\BroadcastRecipient;
use Illuminate\Support\Facades\Auth;

/**
 * Tandai satu penerima terkirim/dilewati — jalur manual lewat tautan wa.me.
 */
class UpdateRecipientStatusAction
{
    public function handle(BroadcastRecipient $recipient, BroadcastRecipientStatus $status): BroadcastRecipient
    {
        $old = $recipient->status;

        $recipient->update([
            'status' => $status,
            'sent_at' => $status === BroadcastRecipientStatus::Sent ? now() : null,
            'error' => null,
        ]);

        app(LogAuditAction::class)->handle(
            'broadcast.recipient_updated',
            $recipient,
            Auth::user(),
            ['old' => ['status' => $old], 'new' => ['status' => $status]],
            'Menandai pesan untuk '.$recipient->name.' sebagai '.$status->label().'.',
        );

        return $recipient;
    }
}
