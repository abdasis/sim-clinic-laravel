<?php

namespace App\Support;

use App\Enums\BroadcastAudience;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Susun daftar penerima broadcast beserta variabel templatnya.
 *
 * Satu nomor hanya menerima satu pesan walau dipakai dua pasien (ibu dan
 * anak sering memakai nomor yang sama); pasien tanpa nomor valid dilaporkan
 * terpisah supaya admin tahu siapa yang tidak terjangkau.
 *
 * Yang menolak promosi juga dihitung dan dilaporkan, tidak sekadar disaring
 * diam-diam. Pada penerima yang dipilih sendiri satu per satu, hilangnya
 * seseorang dari daftar tanpa keterangan terbaca sebagai sistem yang rusak —
 * padahal ia justru sedang menghormati pilihan pasien itu.
 */
class BroadcastAudienceBuilder
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{
     *     recipients: Collection<int, array<string, mixed>>,
     *     without_phone: int,
     *     opted_out: int
     * }
     */
    public function build(BroadcastAudience $audience, array $params = [], bool $marketing = true): array
    {
        $patients = $this->patientsFor($audience, $params, $marketing);
        $optedOut = $marketing ? $this->optedOutCount($audience, $params) : 0;
        $lastVisits = $this->lastVisits($patients->pluck('id'));

        $withoutPhone = 0;
        $seenPhones = [];
        $recipients = collect();

        foreach ($patients as $patient) {
            $phone = PhoneNumber::normalize($patient->whatsapp);

            if ($phone === null) {
                $withoutPhone++;

                continue;
            }

            if (isset($seenPhones[$phone])) {
                continue;
            }

            $seenPhones[$phone] = true;
            $visit = $lastVisits[$patient->id] ?? null;

            $recipients->push([
                'patient_id' => $patient->id,
                'name' => $patient->name,
                'phone' => $phone,
                'variables' => [
                    'nama' => $patient->name,
                    'layanan_terakhir' => $visit->last_service ?? '-',
                    'tanggal_terakhir' => $visit
                        ? Carbon::parse($visit->last_at)->translatedFormat('d F Y')
                        : '-',
                    // Dihitung per hari kalender, bukan per 24 jam: kunjungan
                    // pukul 10.00 yang diingatkan pukul 08.00 tetap "40 hari",
                    // bukan "39 hari" hanya karena jam kirimnya lebih pagi.
                    // Angkanya juga harus sama dengan ambang aturan pengingat
                    // yang memicunya.
                    'hari_sejak_kunjungan' => $visit
                        ? (string) Carbon::parse($visit->last_at)
                            ->startOfDay()
                            ->diffInDays(now()->startOfDay())
                        : '-',
                ],
            ]);
        }

        return [
            'recipients' => $recipients,
            'without_phone' => $withoutPhone,
            'opted_out' => $optedOut,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Collection<int, Patient>
     */
    private function patientsFor(BroadcastAudience $audience, array $params, bool $marketing): Collection
    {
        $query = $this->scoped($audience, $params);

        // Promosi hanya untuk pasien yang memberi izin; pengingat operasional
        // perawatan tidak tunduk pada opt-in pemasaran.
        if ($marketing) {
            $query->where('whatsapp_opt_in', true);
        }

        return $query->get();
    }

    /**
     * Berapa banyak yang cocok dengan sasarannya tapi menolak promosi.
     *
     * @param  array<string, mixed>  $params
     */
    private function optedOutCount(BroadcastAudience $audience, array $params): int
    {
        return $this->scoped($audience, $params)
            ->where(fn ($query) => $query
                ->where('whatsapp_opt_in', false)
                ->orWhereNull('whatsapp_opt_in'))
            ->count();
    }

    /**
     * Penyaring sasaran tanpa urusan izin promosi.
     *
     * @param  array<string, mixed>  $params
     * @return Builder<Patient>
     */
    private function scoped(BroadcastAudience $audience, array $params)
    {
        $query = Patient::query()->orderBy('name');

        if ($audience === BroadcastAudience::Selected) {
            $ids = array_filter(array_map('intval', (array) ($params['patient_ids'] ?? [])));

            // Daftar kosong berarti tidak ada yang dipilih, bukan berarti
            // semua pasien — whereIn([]) yang kosong sudah menjamin itu,
            // tapi ditulis tegas supaya niatnya terbaca.
            $query->whereIn('id', $ids === [] ? [0] : $ids);
        }

        if ($audience === BroadcastAudience::Inactive) {
            $days = max(1, (int) ($params['days'] ?? 30));
            $cutoff = now()->subDays($days);

            // Pengingat kembali hanya bermakna untuk pasien yang pernah
            // datang; kunjungan terakhirnya harus lebih tua dari ambang.
            $query->whereHas('transactions', fn ($q) => $q->whereNull('cancelled_at'))
                ->whereDoesntHave('transactions', fn ($q) => $q
                    ->whereNull('cancelled_at')
                    ->where('issued_at', '>', $cutoff));
        }

        if ($audience === BroadcastAudience::Service) {
            $serviceId = (int) ($params['service_id'] ?? 0);

            $query->whereHas('transactions', fn ($q) => $q
                ->whereNull('cancelled_at')
                ->whereHas('items', fn ($item) => $item->where('service_id', $serviceId)));
        }

        return $query;
    }

    /**
     * Kunjungan terakhir per pasien dalam satu kueri: tanggal dan nama
     * layanan pada transaksi itu.
     *
     * @param  Collection<int, int>  $patientIds
     * @return Collection<int, object>
     */
    private function lastVisits(Collection $patientIds): Collection
    {
        if ($patientIds->isEmpty()) {
            return collect();
        }

        $tenantId = app('tenant')->id;

        $lastAt = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->whereNull('cancelled_at')
            ->whereIn('patient_id', $patientIds)
            ->groupBy('patient_id')
            ->selectRaw('patient_id, MAX(issued_at) as last_at');

        return DB::table('transactions')
            ->joinSub($lastAt, 'last', fn ($join) => $join
                ->on('transactions.patient_id', '=', 'last.patient_id')
                ->on('transactions.issued_at', '=', 'last.last_at'))
            ->leftJoin('transaction_items', fn ($join) => $join
                ->on('transaction_items.transaction_id', '=', 'transactions.id')
                ->whereNotNull('transaction_items.service_id'))
            ->groupBy('transactions.patient_id', 'last.last_at')
            ->selectRaw('transactions.patient_id, last.last_at, MIN(transaction_items.name) as last_service')
            ->get()
            ->keyBy('patient_id');
    }
}
