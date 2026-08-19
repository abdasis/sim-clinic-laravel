<?php

namespace App\Actions\Chatbot;

use App\Models\ChatMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Percakapan yang menggantung: bot bicara terakhir, lalu sunyi.
 *
 * Yang dicari bukan sekadar "lama tidak ada pesan". Percakapan yang pesan
 * terakhirnya dari pasien berarti botnya yang belum menjawab — menutupnya
 * justru menyembunyikan kegagalan. Yang layak ditutup hanya percakapan yang
 * bolanya ada di pasien.
 */
class FindIdleConversationsAction
{
    /**
     * Jarak terjauh yang masih dianggap "baru saja". Tanpa batas ini, klinik
     * yang baru menyalakan fitur akan mengirim penutup ke seluruh percakapan
     * lama sekaligus — termasuk yang sudah basi berbulan-bulan.
     */
    private const STALE_MULTIPLIER = 6;

    private const STALE_FLOOR_MINUTES = 60;

    /**
     * @return Collection<int, ChatMessage> pesan terakhir tiap percakapan yang siap ditutup
     */
    public function handle(int $idleMinutes, ?Carbon $now = null): Collection
    {
        $now ??= Carbon::now();

        $until = $now->copy()->subMinutes($idleMinutes);
        $since = $now->copy()->subMinutes(max($idleMinutes * self::STALE_MULTIPLIER, self::STALE_FLOOR_MINUTES));

        // Satu baris per nomor: yang terbaru. Dibandingkan lewat id, bukan
        // created_at — dua pesan bisa lahir pada detik yang sama saat bot
        // menjawab cepat, dan urutan id tidak pernah ambigu.
        $latestIds = ChatMessage::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('sender_phone')
            ->pluck('id');

        return ChatMessage::query()
            ->whereIn('id', $latestIds)
            ->where('direction', 'out')
            ->where('role', 'assistant')
            ->where('is_closing', false)
            ->whereBetween('created_at', [$since, $until])
            ->orderBy('id')
            ->get();
    }
}
