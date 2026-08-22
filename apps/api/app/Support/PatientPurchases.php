<?php

namespace App\Support;

use App\Models\MedicalRecord;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menautkan belanja pasien ke kunjungan yang tepat di rekam medisnya.
 *
 * Sebelumnya kolom OBT/HCP dibaca dari `rekam medis → booking → transaksi`,
 * jadi produk hanya muncul kalau kasir kebetulan memilih booking-nya saat
 * menjual. Padahal kasir hanya wajib memilih pasien; booking opsional, dan
 * catatan walk-in tidak punya booking sama sekali sehingga kolomnya pasti
 * kosong selamanya.
 *
 * Yang dipakai sekarang pasien dan tanggalnya. Dokter yang perlu tahu
 * skincare apa yang sedang dipakai pasien — sebelum memutuskan boleh
 * tidaknya peeling, atau saat menelusuri iritasi — tidak boleh bergantung
 * pada ingatan kasir menekan satu kolom opsional.
 *
 * Pencocokannya tidak berhenti di tanggal kalender. Klinik yang tutup malam
 * kerap merampungkan catatannya lewat tengah malam, jadi belanja pukul 23.30
 * dan kunjungan yang tercatat keesokan paginya adalah peristiwa yang sama —
 * dan batas tanggal memisahkan keduanya persis di tempat yang salah. Selain
 * hari yang sama, nota juga menempel ke kunjungan terdekat dalam rentang
 * WINDOW_HOURS.
 *
 * Satu nota ditautkan ke tepat satu kunjungan, tidak pernah dua: bila ada
 * beberapa yang memenuhi syarat, yang paling dekat waktunya menang, dan
 * seri diputus oleh catatan yang lebih dulu dibuat. Nota yang tidak
 * berpapasan dengan kunjungan mana pun tidak hilang — ia tetap terbaca di
 * riwayat pembelian pasien, hanya tidak menempel ke baris kunjungan.
 */
class PatientPurchases
{
    /**
     * Selisih waktu terjauh yang masih dianggap satu peristiwa.
     *
     * Dua belas jam menutup kasus yang benar-benar terjadi — klinik tutup
     * malam, catatan dirampungkan pagi berikutnya — tanpa menjangkau
     * kunjungan lusa yang jelas peristiwa lain.
     */
    private const WINDOW_HOURS = 12;

    /** @var array<int, list<Transaction>> nota per id rekam medis */
    private array $byRecord = [];

    /**
     * Siapkan seluruh baris satu halaman sekaligus.
     *
     * @param  Collection<int, MedicalRecord>  $records
     */
    public function preload(int $patientId, Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        // Calon penerima dihitung dari seluruh catatan pasien, bukan dari
        // halaman ini saja: satu hari bisa terbelah dua halaman, dan kalau
        // pemenangnya dipilih per halaman, nota yang sama menempel dua kali.
        $candidates = self::candidates($patientId);

        foreach ($this->transactions($patientId, $records) as $transaction) {
            $recordId = self::owner($transaction, $candidates);

            if ($recordId !== null && $records->contains('id', $recordId)) {
                $this->byRecord[$recordId][] = $transaction;
            }
        }
    }

    /**
     * Nota milik satu kunjungan.
     *
     * @return list<Transaction>
     */
    public function forRecord(MedicalRecord $record): array
    {
        return $this->byRecord[$record->id] ?? [];
    }

    /** Waktu kunjungan, bukan waktu catatannya ditulis bila keduanya beda. */
    public static function recordTime(MedicalRecord $record): ?Carbon
    {
        $at = $record->booking?->start_at ?? $record->created_at;

        return $at === null ? null : Carbon::parse($at);
    }

    /** Waktu nota mengikuti waktu terbitnya, yang boleh diisi mundur. */
    public static function transactionTime(Transaction $transaction): ?Carbon
    {
        $at = $transaction->issued_at ?? $transaction->created_at;

        return $at === null ? null : Carbon::parse($at);
    }

    /**
     * Kunjungan mana yang menerima nota ini.
     *
     * Nota yang menyebut booking suatu catatan menempel ke catatan itu —
     * tautan yang paling pasti, jadi ia mendahului pencocokan waktu. Sisanya
     * jatuh ke kunjungan terdekat yang masih dalam jangkauan.
     *
     * @param  array<int, array{id: int, booking_id: ?int, at: Carbon}>  $candidates
     */
    private static function owner(Transaction $transaction, array $candidates): ?int
    {
        if ($transaction->booking_id !== null) {
            foreach ($candidates as $candidate) {
                if ($candidate['booking_id'] === $transaction->booking_id) {
                    return $candidate['id'];
                }
            }
        }

        $at = self::transactionTime($transaction);

        if ($at === null) {
            return null;
        }

        $winner = null;
        $closest = null;

        foreach ($candidates as $candidate) {
            if (! self::withinReach($at, $candidate['at'])) {
                continue;
            }

            $gap = $at->diffInSeconds($candidate['at'], absolute: true);

            // Seri diputus catatan yang lebih dulu dibuat: candidates sudah
            // urut id, jadi perbandingan tegas menahan pemenang pertama.
            if ($closest === null || $gap < $closest) {
                $winner = $candidate['id'];
                $closest = $gap;
            }
        }

        return $winner;
    }

    /**
     * Satu hari yang sama selalu terjangkau — itu perilaku yang sudah ada dan
     * tidak boleh menyempit; di luar itu, selisihnya yang menentukan.
     */
    private static function withinReach(Carbon $at, Carbon $visit): bool
    {
        return $at->isSameDay($visit)
            || $at->diffInHours($visit, absolute: true) <= self::WINDOW_HOURS;
    }

    /**
     * Seluruh kunjungan pasien beserta waktunya, urut dari yang paling dulu
     * dibuat.
     *
     * @return array<int, array{id: int, booking_id: ?int, at: Carbon}>
     */
    private static function candidates(int $patientId): array
    {
        return MedicalRecord::query()
            ->where('patient_id', $patientId)
            ->with('booking:id,start_at')
            ->orderBy('id')
            ->get(['id', 'booking_id', 'created_at'])
            ->map(fn (MedicalRecord $record): ?array => ($at = self::recordTime($record)) === null
                ? null
                : ['id' => $record->id, 'booking_id' => $record->booking_id, 'at' => $at])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Nota pasien yang mungkin berpapasan dengan kunjungan di halaman ini.
     *
     * Nota batal dilewati: barang yang notanya dibatalkan tidak pernah sampai
     * ke tangan pasien, dan mencantumkannya di rekam medis berarti dokter
     * membaca pemakaian yang tidak pernah terjadi.
     *
     * @param  Collection<int, MedicalRecord>  $records
     * @return Collection<int, Transaction>
     */
    private function transactions(int $patientId, Collection $records): Collection
    {
        $times = $records->map(fn (MedicalRecord $record) => self::recordTime($record))->filter();

        if ($times->isEmpty()) {
            return collect();
        }

        // Rentangnya dilebihkan sehari di kedua ujung: jangkauan dihitung dari
        // selisih jam, dan memotongnya persis di batas tanggal akan membuang
        // justru nota lewat tengah malam yang hendak dijangkau.
        $from = $times->min()->copy()->subDay()->startOfDay();
        $to = $times->max()->copy()->addDay()->endOfDay();

        return Transaction::query()
            ->with('items', 'performers:id,name')
            ->where('patient_id', $patientId)
            ->whereNull('cancelled_at')
            ->where(fn (Builder $query) => $query
                ->whereBetween('issued_at', [$from, $to])
                ->orWhere(fn (Builder $fallback) => $fallback
                    ->whereNull('issued_at')
                    ->whereBetween('created_at', [$from, $to])))
            ->get();
    }
}
