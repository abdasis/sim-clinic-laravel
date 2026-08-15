<?php

namespace App\Services;

use App\Actions\Broadcast\CreateBroadcastAction;
use App\Actions\Broadcast\DeleteBroadcastAction;
use App\Actions\Broadcast\SendBroadcastViaGatewayAction;
use App\Actions\Broadcast\UpdateRecipientStatusAction;
use App\Enums\BroadcastRecipientStatus;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi broadcast WhatsApp: pembuatan snapshot penerima dalam satu
 * transaksi, penandaan manual, dan blast lewat gateway.
 */
class BroadcastService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Broadcast
    {
        return DB::transaction(fn () => app(CreateBroadcastAction::class)->handle($data));
    }

    public function delete(Broadcast $broadcast): void
    {
        DB::transaction(fn () => app(DeleteBroadcastAction::class)->handle($broadcast));
    }

    public function markRecipient(BroadcastRecipient $recipient, BroadcastRecipientStatus $status): BroadcastRecipient
    {
        return app(UpdateRecipientStatusAction::class)->handle($recipient, $status);
    }

    /**
     * @return array{sent: int, failed: int}
     */
    public function sendViaGateway(Broadcast $broadcast): array
    {
        $setting = WhatsappSetting::query()->first();

        if ($setting === null || ! $setting->isGatewayReady()) {
            abort(422, __('broadcast.gateway_not_ready'));
        }

        // Sengaja TANPA transaction pembungkus: tiap penerima menulis
        // statusnya sendiri agar kegagalan di tengah tidak menghapus jejak
        // pesan yang sudah benar-benar terkirim.
        return app(SendBroadcastViaGatewayAction::class)->handle($broadcast, $setting);
    }
}
