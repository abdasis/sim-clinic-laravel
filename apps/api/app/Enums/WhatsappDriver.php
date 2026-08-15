<?php

namespace App\Enums;

enum WhatsappDriver: string
{
    /** Tautan wa.me per pasien; dikirim dari WhatsApp klinik sendiri. */
    case Manual = 'manual';

    /** Gateway HTTP (kompatibel Fonnte/Wablas); kirim massal dari server. */
    case Gateway = 'gateway';

    /** Sidecar internal: scan QR dari WhatsApp klinik, kirim dari server. */
    case Qr = 'qr';

    public function label(): string
    {
        return __('broadcast.driver.'.$this->value);
    }
}
