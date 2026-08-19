<?php

namespace App\Support;

use App\Enums\CommissionRuleType;
use App\Models\CommissionRule;
use App\Models\CommissionSetting;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Hitung fee terapis untuk satu periode berdasarkan aturan yang tersimpan.
 *
 * Perhitungan sengaja dipisah dari pencatatan: hasilnya ditinjau dulu oleh
 * admin, baru dibukukan sebagai pengeluaran. Angka fee tidak pernah muncul
 * di buku kas tanpa ada yang menyetujuinya.
 *
 * Satuan "pasien" adalah satu kunjungan berbayar (satu transaksi), mengikuti
 * laporan bulanan klinik. Bonus pasien baru jatuh ke PEMBAWANYA — kolom
 * referred_by pasien — dan satu pasien hanya menghasilkan satu bonus untuk
 * satu orang, sekali seumur hidup pasien itu.
 *
 * Tarif dinilai per tanggal transaksi, bukan per tarif yang kebetulan aktif
 * saat tombol hitung ditekan: menaikkan fee di tengah bulan tidak boleh ikut
 * mengubah paruh bulan yang sudah lewat.
 */
class CommissionCalculator
{
    /**
     * Fee yang jatuh ke tiap transaksi, dikumpulkan sambil menghitung.
     *
     * @var array<int, float>
     */
    private array $byTransaction = [];

    public function __construct(
        private readonly string $from,
        private readonly string $to,
    ) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: float, rules_used: array<int, string>, by_transaction: array<int, float>}
     */
    public function run(): array
    {
        $this->byTransaction = [];

        // Semua versi yang menyentuh periode ini, bukan hanya yang berlaku
        // sekarang — tarif bulan lalu tetap dibutuhkan untuk menghitungnya.
        $rules = CommissionRule::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereNull('effective_from')
                ->orWhere('effective_from', '<=', $this->to.' 23:59:59'))
            ->where(fn ($q) => $q
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>', $this->from.' 00:00:00'))
            ->get();

        $transactions = Transaction::query()
            ->whereNull('cancelled_at')
            ->whereDate('issued_at', '>=', $this->from)
            ->whereDate('issued_at', '<=', $this->to)
            ->with(['items', 'performers'])
            ->get();

        $newPatients = $this->newPatientsByBeneficiary();

        // Satu orang bisa muncul lewat tiga jalan berbeda: mengerjakan
        // kunjungan, menawarkan barang, atau membawa pasien baru. Ketiganya
        // dikumpulkan dulu supaya tiap orang tetap satu baris.
        $rows = collect();

        // Peran yang berhak adalah setelan klinik, bukan daftar tetap di kode:
        // kasir belum ikut sekarang, dan saat nanti diikutkan yang berubah
        // cukup setelannya.
        $eligibleRoles = CommissionSetting::eligibleRoles();

        foreach ($this->beneficiaryIds($transactions, $newPatients) as $userId) {
            $user = User::find($userId);

            if ($user === null || ! in_array($user->clinic_role?->value, $eligibleRoles, true)) {
                continue;
            }

            $rows->put($userId, $this->rowFor(
                $user,
                $rules,
                $transactions,
                collect($newPatients[$userId] ?? []),
            ));
        }

        $rows = $rows
            ->filter(fn (array $row) => $row['total'] > 0 || $row['visits'] > 0)
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'total' => (float) collect($rows)->sum('total'),
            'rules_used' => $rules
                ->map(fn (CommissionRule $rule) => $rule->name)
                ->unique()
                ->values()
                ->all(),
            'by_transaction' => $this->byTransaction,
        ];
    }

    /**
     * Catat tiap fee ke nota asalnya.
     *
     * Laporan bulanan klinik menaruh fee di kolom tersendiri pada baris
     * kunjungannya, bukan hanya sebagai total per orang di bawah — tanpa
     * penempelan ini, kolom "total bersih" per baris tidak punya dasar hitung.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     */
    private function attributeToTransactions(Collection $lines): void
    {
        foreach ($lines as $line) {
            $transactionId = $line['transaction_id'] ?? null;

            if ($transactionId === null) {
                continue;
            }

            $this->byTransaction[$transactionId] = round(
                ($this->byTransaction[$transactionId] ?? 0) + (float) $line['amount'],
                2,
            );
        }
    }

    /**
     * Semua orang yang berhak diperiksa di periode ini.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @param  array<int, array<int, CarbonInterface>>  $newPatients
     * @return array<int, int>
     */
    private function beneficiaryIds(Collection $transactions, array $newPatients): array
    {
        $performers = $transactions
            ->flatMap(fn (Transaction $t) => $t->performers->pluck('id'));

        $sellers = $transactions
            ->flatMap(fn (Transaction $t) => $t->items->pluck('offered_by'))
            ->filter();

        return $performers
            ->merge($sellers)
            ->merge(array_keys($newPatients))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Aturan yang berlaku untuk satu orang pada satu saat.
     *
     * Aturan yang disetel khusus seseorang MENGGANTIKAN aturan umum sejenis,
     * bukan ditambahkan padanya. Tanpa ini, dokter dengan fee 15rb tetap ikut
     * menerima fee umum 5rb dan pulang membawa 20rb.
     *
     * @param  Collection<int, CommissionRule>  $rules
     * @return Collection<int, CommissionRule>
     */
    private function rulesFor(Collection $rules, ?int $userId, CarbonInterface $moment): Collection
    {
        $applicable = $rules
            ->filter(fn (CommissionRule $rule) => $rule->appliesTo($userId))
            // `effective_from` kosong = tarif perdana, berlaku sejak awal.
            ->filter(fn (CommissionRule $rule) => ($rule->effective_from === null
                || $rule->effective_from->lessThanOrEqualTo($moment))
                && ($rule->effective_to === null || $rule->effective_to->greaterThan($moment)));

        return $applicable
            ->groupBy(fn (CommissionRule $rule) => $rule->type->value)
            ->map(function (Collection $ofType) use ($userId) {
                $personal = $ofType->filter(
                    fn (CommissionRule $rule) => $rule->therapist_id === $userId && $userId !== null
                );

                return $personal->isNotEmpty() ? $personal : $ofType;
            })
            ->flatten()
            ->values();
    }

    /**
     * Kunjungan yang berhak atas fee per pasien untuk satu orang: yang ia
     * kerjakan, dan yang benar-benar memuat tindakan. Penjualan produk murni
     * tidak menghasilkan fee — yang dibayar adalah pekerjaan merawat pasien,
     * bukan menjual barang. Omzet untuk komisi penjualan tetap ikut produk.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, Transaction>
     */
    private function treatmentVisits(Collection $transactions, int $userId): Collection
    {
        return $transactions
            ->filter(fn (Transaction $t) => $t->performers->contains('id', $userId))
            ->filter(fn (Transaction $t) => $t->items->contains(
                fn ($item) => $item->service_id !== null
            ));
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     * @param  Collection<int, Transaction>  $transactions
     * @param  Collection<int, array{at: CarbonInterface, transaction_id: int|null}>  $newPatientMoments
     * @return array<string, mixed>
     */
    private function rowFor(
        User $beneficiary,
        Collection $rules,
        Collection $transactions,
        Collection $newPatientMoments,
    ): array {
        $userId = $beneficiary->id;
        $visits = $this->treatmentVisits($transactions, $userId);
        $sold = $this->soldLines($transactions, $userId);
        $revenue = (float) $sold->sum(fn (array $line) => $line['subtotal']);

        $raw = collect()
            ->merge($this->perPatientLines($rules, $userId, $visits))
            ->merge($this->newPatientLines($rules, $userId, $newPatientMoments))
            ->merge($this->revenueLines($rules, $userId, $sold, $revenue));

        $this->attributeToTransactions($raw);

        $lines = $this->fold($raw->all());

        return [
            'therapist_id' => $userId,
            'therapist_name' => $beneficiary->name,
            'revenue' => $revenue,
            'visits' => $visits->count(),
            'new_patients' => $newPatientMoments->count(),
            'lines' => $lines->values()->all(),
            'total' => (float) $lines->sum('amount'),
        ];
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     * @param  Collection<int, Transaction>  $visits
     * @return Collection<int, array<string, mixed>>
     */
    private function perPatientLines(Collection $rules, ?int $userId, Collection $visits): Collection
    {
        $lines = [];

        foreach ($visits as $visit) {
            $moment = Carbon::parse($visit->issued_at);

            foreach ($this->rulesFor($rules, $userId, $moment) as $rule) {
                if ($rule->type !== CommissionRuleType::PerPatient) {
                    continue;
                }

                $lines[] = [
                    'rule' => $rule,
                    'amount' => (float) $rule->amount,
                    'basis' => 1,
                    'transaction_id' => $visit->id,
                ];
            }
        }

        return collect($lines);
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     * @param  Collection<int, array{at: CarbonInterface, transaction_id: int|null}>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function newPatientLines(Collection $rules, ?int $userId, Collection $entries): Collection
    {
        $lines = [];

        foreach ($entries as $entry) {
            foreach ($this->rulesFor($rules, $userId, $entry['at']) as $rule) {
                if ($rule->type !== CommissionRuleType::PerNewPatient) {
                    continue;
                }

                $lines[] = [
                    'rule' => $rule,
                    'amount' => (float) $rule->amount,
                    'basis' => 1,
                    'transaction_id' => $entry['transaction_id'],
                ];
            }
        }

        return collect($lines);
    }

    /**
     * Baris jualan yang ditawarkan orang ini. Yang tidak ada penawarnya —
     * pasien membeli atas kemauan sendiri — tidak masuk target siapa pun.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, array{subtotal: float, at: CarbonInterface, transaction_id: int}>
     */
    private function soldLines(Collection $transactions, int $userId): Collection
    {
        return $transactions->flatMap(fn (Transaction $t) => $t->items
            ->filter(fn ($item) => (int) $item->offered_by === $userId)
            ->map(fn ($item) => [
                'subtotal' => (float) $item->subtotal,
                'at' => Carbon::parse($t->issued_at),
                'transaction_id' => $t->id,
            ]));
    }

    /**
     * Ambangnya dinilai dari omzet orang itu sepanjang periode — "10 juta
     * sebulan" memang begitu bacanya. Persentasenya sendiri mengikuti tarif
     * yang berlaku pada tanggal tiap transaksi.
     *
     * @param  Collection<int, CommissionRule>  $rules
     * @param  Collection<int, array{subtotal: float, at: CarbonInterface, transaction_id: int}>  $sold
     * @return Collection<int, array<string, mixed>>
     */
    private function revenueLines(
        Collection $rules,
        ?int $userId,
        Collection $sold,
        float $periodRevenue,
    ): Collection {
        $lines = [];

        foreach ($sold as $line) {
            $moment = $line['at'];
            $subtotal = $line['subtotal'];

            $tiers = $this->rulesFor($rules, $userId, $moment)
                ->filter(fn (CommissionRule $rule) => $rule->type === CommissionRuleType::RevenuePercent)
                ->filter(fn (CommissionRule $rule) => $periodRevenue >= (float) $rule->min_revenue);

            // Dari beberapa tingkat, hanya ambang tertinggi yang terlampaui
            // yang berlaku — 5% dan 6% tidak pernah dijumlahkan.
            $winner = $tiers->sortByDesc(fn (CommissionRule $rule) => (float) $rule->min_revenue)->first();

            if ($winner === null) {
                continue;
            }

            $lines[] = [
                'rule' => $winner,
                'amount' => $subtotal * (float) $winner->percent / 100,
                'basis' => $subtotal,
                'transaction_id' => $line['transaction_id'],
            ];
        }

        return collect($lines);
    }

    /**
     * Gabungkan baris sejenis jadi satu ringkasan. Satu periode bisa memakai
     * dua versi tarif dengan nama sama; yang dibaca admin tetap satu baris
     * per aturan, dengan persentase dikosongkan bila memang berbeda-beda.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    private function fold(array $lines): Collection
    {
        return collect($lines)
            ->groupBy(fn (array $line) => $line['rule']->name)
            ->map(function (Collection $group) {
                /** @var CommissionRule $rule */
                $rule = $group->first()['rule'];
                $percents = $group
                    ->map(fn (array $line) => (float) $line['rule']->percent)
                    ->unique();

                return [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'type' => $rule->type,
                    'basis' => $group->sum('basis'),
                    'percent' => $rule->type === CommissionRuleType::RevenuePercent && $percents->count() === 1
                        ? $percents->first()
                        : null,
                    'amount' => round((float) $group->sum('amount'), 2),
                ];
            })
            ->filter(fn (array $line) => $line['amount'] > 0)
            ->values();
    }

    /**
     * Pasien baru pada periode ini beserta saat kunjungan pertamanya,
     * dikelompokkan per penerima bonus. Pasien baru = kunjungan berbayar
     * PERTAMA seumur hidupnya jatuh di dalam periode; kunjungan berikutnya
     * tidak pernah dihitung lagi.
     *
     * Bonus hanya untuk yang tercatat membawa pasien itu. Dulu ada jalan
     * mundur ke terapis kunjungan pertama bila kolom pembawanya kosong —
     * itu menebak, dan sejak satu kunjungan boleh ditangani lebih dari satu
     * orang, tebakannya tidak punya dasar sama sekali.
     *
     * @return array<int, array<int, array{at: CarbonInterface, transaction_id: int|null}>> user_id => kunjungan pertama
     */
    private function newPatientsByBeneficiary(): array
    {
        $firstVisits = Transaction::query()
            ->whereNull('cancelled_at')
            ->selectRaw('patient_id, MIN(issued_at) as first_at')
            ->groupBy('patient_id')
            ->havingRaw('MIN(issued_at) >= ?', [$this->from.' 00:00:00'])
            ->havingRaw('MIN(issued_at) <= ?', [$this->to.' 23:59:59'])
            ->pluck('first_at', 'patient_id');

        if ($firstVisits->isEmpty()) {
            return [];
        }

        $referrers = Patient::query()
            ->whereIn('id', $firstVisits->keys())
            ->whereNotNull('referred_by')
            ->pluck('referred_by', 'id');

        if ($referrers->isEmpty()) {
            return [];
        }

        // Nota kunjungan pertamanya, supaya bonusnya bisa ditempelkan ke baris
        // laporan yang benar — bukan hanya menumpuk di total bulanan.
        $firstTransactions = Transaction::query()
            ->whereNull('cancelled_at')
            ->whereIn('patient_id', $referrers->keys())
            ->orderBy('issued_at')
            ->get(['id', 'patient_id', 'issued_at'])
            ->groupBy('patient_id')
            ->map(fn (Collection $group) => $group->first());

        $byBeneficiary = [];

        foreach ($referrers as $patientId => $beneficiary) {
            $byBeneficiary[$beneficiary][] = [
                'at' => Carbon::parse($firstVisits[$patientId]),
                'transaction_id' => $firstTransactions->get($patientId)?->id,
            ];
        }

        return $byBeneficiary;
    }
}
