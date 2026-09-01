<?php

namespace App\Support;

/**
 * Keadaan sesi WhatsApp seperti yang dilaporkan gateway.
 *
 * Dipakai untuk memilah kegagalan kirim, karena kode status HTTP saja tidak
 * bisa: WAHA menjawab 422 untuk sesi terputus maupun nomor yang ditolak, dan
 * 500 untuk halaman WhatsApp Web-nya yang dimuat ulang. Yang membedakan
 * "nomor ini bermasalah" dari "gatewaynya sedang tidak bisa mengirim"
 * hanyalah keadaan sesinya saat itu.
 */
enum WahaSessionState: string
{
    case Working = 'WORKING';
    case Starting = 'STARTING';
    case ScanQrCode = 'SCAN_QR_CODE';
    case Stopped = 'STOPPED';
    case Failed = 'FAILED';
    case NotFound = 'NOT_FOUND';
    /** Status yang tidak dikenal, termasuk saat gatewaynya tidak bisa ditanya. */
    case Unknown = 'UNKNOWN';

    public static function parse(?string $status): self
    {
        return self::tryFrom((string) $status) ?? self::Unknown;
    }

    /**
     * Sesi yang tidak akan pulih tanpa orang: QR-nya perlu dipindai ulang,
     * atau sesinya perlu dinyalakan lagi.
     *
     * Seluruh sisa antrean akan ditolak dengan cara yang sama persis, jadi
     * meneruskan blast hanya menghanguskan penerima yang nomornya tidak
     * salah apa-apa.
     */
    public function needsAttention(): bool
    {
        return in_array($this, [self::ScanQrCode, self::Stopped, self::Failed, self::NotFound], true);
    }

    /**
     * Sesi yang sedang bangkit sendiri.
     *
     * Halaman WhatsApp Web milik gateway kerap dimuat ulang di tengah blast —
     * gejalanya "Execution context was destroyed" — lalu pulih sendiri dalam
     * hitungan detik. Yang begini ditunggu, bukan dijadikan alasan
     * menghentikan campaign.
     */
    public function isRecovering(): bool
    {
        return $this === self::Starting || $this === self::Unknown;
    }
}
