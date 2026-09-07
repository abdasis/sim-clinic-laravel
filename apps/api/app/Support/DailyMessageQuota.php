<?php

namespace App\Support;

use App\Models\BroadcastRecipient;
use App\Models\WhatsappSetting;

/**
 * Jatah pesan keluar klinik untuk hari ini.
 *
 * Jeda antar pesan menjaga irama kiriman, tapi yang paling sering membuat
 * nomor WhatsApp diblokir justru volume hariannya — dan itu tidak terlihat
 * dari jeda mana pun. Beberapa campaign yang dijalankan berurutan bisa
 * mengeluarkan ratusan pesan dari satu nomor tanpa ada yang menghitung.
 *
 * Yang dihitung pesan yang benar-benar berangkat (punya `sent_at` hari ini),
 * bukan yang diantrekan: antrean bisa dibatalkan, dijeda, atau gagal, dan
 * yang membuat nomor diblokir cuma yang sungguh terkirim.
 */
class DailyMessageQuota
{
    public function __construct(
        public readonly int $limit,
        public readonly int $used,
    ) {}

    public static function today(): self
    {
        $limit = WhatsappSetting::query()->first()?->daily_message_limit
            ?: (int) config('broadcast.daily_limit');

        $used = BroadcastRecipient::query()
            // Rentang antara awal dan akhir hari, bukan whereDate: kolom yang
            // dibungkus fungsi tanggal tidak bisa memakai index-nya.
            ->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        return new self($limit, $used);
    }

    public function remaining(): int
    {
        return max(0, $this->limit - $this->used);
    }

    public function isSpent(): bool
    {
        return $this->remaining() === 0;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'limit' => $this->limit,
            'used' => $this->used,
            'remaining' => $this->remaining(),
        ];
    }
}
