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
 * Satu nota ditautkan ke tepat satu kunjungan, tidak pernah dua: kalau
 * seorang pasien punya lebih dari satu catatan di tanggal yang sama,
 * belanjanya menempel di catatan paling awal hari itu. Nota yang tanggalnya
 * tidak berpapasan dengan kunjungan mana pun tidak hilang — ia tetap terbaca
 * di riwayat pembelian pasien, hanya tidak menempel ke baris kunjungan.
 */
class PatientPurchases
{
    /** @var array<int, list<Transaction>> nota per id rekam medis */
    private array $byRecord = [];

    /**
     * Siapkan seluruh baris satu halaman sekaligus.
     *
     * @param  Collection<int, MedicalRecord>  $records
     */
    public function preload(int $patientId, Collection $records): void
    {
        $dates = $records->map(fn (MedicalRecord $record) => self::recordDate($record))
            ->filter()
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return;
        }

        // Pemenang per tanggal dihitung dari seluruh catatan pasien, bukan dari
        // halaman ini saja: satu tanggal bisa terbelah dua halaman, dan kalau
        // pemenangnya dipilih per halaman, nota yang sama menempel dua kali.
        $winners = self::winnersByDate($patientId, $dates->all());

        foreach ($this->transactions($patientId, $dates->all()) as $transaction) {
            $recordId = $this->owner($transaction, $records, $winners);

            if ($recordId !== null) {
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

    /** Tanggal kunjungan, bukan tanggal catatannya ditulis bila keduanya beda. */
    public static function recordDate(MedicalRecord $record): ?string
    {
        return ($record->booking?->start_at ?? $record->created_at)?->toDateString();
    }

    /** Tanggal nota mengikuti tanggal terbitnya, yang boleh diisi mundur. */
    public static function transactionDate(Transaction $transaction): ?string
    {
        return ($transaction->issued_at ?? $transaction->created_at)?->toDateString();
    }

    /**
     * Nota yang menyebut booking suatu catatan menempel ke catatan itu —
     * tautan yang paling pasti, jadi ia mendahului pencocokan tanggal.
     *
     * @param  Collection<int, MedicalRecord>  $records
     * @param  array<string, int>  $winners
     */
    private function owner(Transaction $transaction, Collection $records, array $winners): ?int
    {
        if ($transaction->booking_id !== null) {
            $exact = $records->firstWhere('booking_id', $transaction->booking_id);

            if ($exact !== null) {
                return $exact->id;
            }
        }

        $date = self::transactionDate($transaction);

        return $date === null ? null : ($winners[$date] ?? null);
    }

    /**
     * Catatan paling awal pada tiap tanggal, dari seluruh riwayat pasien.
     *
     * @param  array<int, string>  $dates
     * @return array<string, int>
     */
    private static function winnersByDate(int $patientId, array $dates): array
    {
        $winners = [];

        MedicalRecord::query()
            ->where('patient_id', $patientId)
            ->with('booking:id,start_at')
            ->orderBy('id')
            ->get(['id', 'booking_id', 'created_at'])
            ->each(function (MedicalRecord $record) use (&$winners, $dates): void {
                $date = self::recordDate($record);

                if ($date === null || ! in_array($date, $dates, true)) {
                    return;
                }

                $winners[$date] ??= $record->id;
            });

        return $winners;
    }

    /**
     * Nota pasien pada tanggal-tanggal yang sedang ditampilkan.
     *
     * Nota batal dilewati: barang yang notanya dibatalkan tidak pernah sampai
     * ke tangan pasien, dan mencantumkannya di rekam medis berarti dokter
     * membaca pemakaian yang tidak pernah terjadi.
     *
     * @param  array<int, string>  $dates
     * @return Collection<int, Transaction>
     */
    private function transactions(int $patientId, array $dates): Collection
    {
        // Dibatasi rentang tanggal halaman ini lebih dulu supaya pasien lama
        // tidak menyeret seluruh notanya berikut isinya ke memori; pencocokan
        // tanggal persisnya baru dilakukan setelahnya, di PHP, karena fungsi
        // tanggal SQL berbeda antara SQLite dan PostgreSQL.
        $sorted = $dates;
        sort($sorted);

        $from = Carbon::parse($sorted[0])->startOfDay();
        $to = Carbon::parse(end($sorted))->endOfDay();

        return Transaction::query()
            ->with('items', 'performers:id,name')
            ->where('patient_id', $patientId)
            ->whereNull('cancelled_at')
            ->where(fn (Builder $query) => $query
                ->whereBetween('issued_at', [$from, $to])
                ->orWhere(fn (Builder $fallback) => $fallback
                    ->whereNull('issued_at')
                    ->whereBetween('created_at', [$from, $to])))
            ->get()
            ->filter(fn (Transaction $transaction) => in_array(
                self::transactionDate($transaction),
                $dates,
                true,
            ))
            ->values();
    }
}
