<?php

namespace App\Support;

use App\Enums\CommissionRuleType;
use App\Models\CommissionRule;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
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
 * referred_by pasien; bila kosong, ke terapis kunjungan pertamanya — dan
 * satu pasien hanya menghasilkan satu bonus untuk satu orang, sekali seumur
 * hidup pasien itu.
 */
class CommissionCalculator
{
    public function __construct(
        private readonly string $from,
        private readonly string $to,
    ) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: float, rules_used: array<int, string>}
     */
    public function run(): array
    {
        $rules = CommissionRule::query()->where('is_active', true)->get();

        $transactions = Transaction::query()
            ->whereNull('cancelled_at')
            ->whereNotNull('therapist_id')
            ->whereDate('issued_at', '>=', $this->from)
            ->whereDate('issued_at', '<=', $this->to)
            ->with('therapist')
            ->get();

        $newPatientCounts = $this->newPatientCountsByBeneficiary();

        $rows = $transactions
            ->groupBy('therapist_id')
            ->map(fn (Collection $group) => $this->rowFor(
                $group->first()->therapist,
                $rules,
                (float) $group->sum(fn (Transaction $t) => (float) $t->subtotal),
                $group->count(),
                (int) ($newPatientCounts[$group->first()->therapist_id] ?? 0),
            ));

        // Pembawa pasien baru yang tidak punya transaksi sendiri (mis. resepsionis
        // yang mereferensikan) tetap dapat barisnya — bonus tidak boleh hangus
        // hanya karena ia bukan terapis yang menangani.
        foreach ($newPatientCounts as $userId => $count) {
            if ($rows->has($userId)) {
                continue;
            }

            $user = User::find($userId);

            if ($user === null) {
                continue;
            }

            $rows->put($userId, $this->rowFor($user, $rules, 0.0, 0, $count));
        }

        $rows = $rows
            ->filter(fn (array $row) => $row['total'] > 0 || $row['visits'] > 0)
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'total' => (float) collect($rows)->sum('total'),
            'rules_used' => $rules->map(fn (CommissionRule $rule) => $rule->name)->all(),
        ];
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     * @return array<string, mixed>
     */
    private function rowFor(
        ?User $beneficiary,
        Collection $rules,
        float $revenue,
        int $visits,
        int $newPatients,
    ): array {
        // Aturan yang dibatasi ke orang lain tidak menyentuh baris ini;
        // kompetisi antar-tingkat pun hanya di antara aturan yang berlaku.
        $rules = $rules
            ->filter(fn (CommissionRule $rule) => $rule->appliesTo($beneficiary?->id))
            ->values();

        $lines = [];

        foreach ($rules as $rule) {
            $line = $this->lineFor($rule, $rules, $revenue, $visits, $newPatients);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return [
            'therapist_id' => $beneficiary?->id,
            'therapist_name' => $beneficiary?->name,
            'revenue' => $revenue,
            'visits' => $visits,
            'new_patients' => $newPatients,
            'lines' => $lines,
            'total' => (float) collect($lines)->sum('amount'),
        ];
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     * @return array<string, mixed>|null
     */
    private function lineFor(
        CommissionRule $rule,
        Collection $rules,
        float $revenue,
        int $visits,
        int $newPatients,
    ): ?array {
        $amount = match ($rule->type) {
            CommissionRuleType::PerPatient => (float) $rule->amount * $visits,
            CommissionRuleType::PerNewPatient => (float) $rule->amount * $newPatients,
            CommissionRuleType::RevenuePercent => $this->isWinningTier($rule, $rules, $revenue)
                ? $revenue * (float) $rule->percent / 100
                : 0.0,
        };

        if ($amount <= 0) {
            return null;
        }

        return [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'type' => $rule->type,
            'basis' => match ($rule->type) {
                CommissionRuleType::PerPatient => $visits,
                CommissionRuleType::PerNewPatient => $newPatients,
                CommissionRuleType::RevenuePercent => $revenue,
            },
            'percent' => $rule->type === CommissionRuleType::RevenuePercent
                ? (float) $rule->percent
                : null,
            'amount' => round($amount, 2),
        ];
    }

    /**
     * Dari beberapa tingkat persentase, hanya ambang tertinggi yang terlampaui
     * yang berlaku — 5% dan 6% tidak pernah dijumlahkan.
     *
     * @param  Collection<int, CommissionRule>  $rules
     */
    private function isWinningTier(CommissionRule $rule, Collection $rules, float $revenue): bool
    {
        if ($revenue < (float) $rule->min_revenue) {
            return false;
        }

        $best = $rules
            ->filter(fn (CommissionRule $other) => $other->type === CommissionRuleType::RevenuePercent
                && $revenue >= (float) $other->min_revenue)
            ->sortByDesc(fn (CommissionRule $other) => (float) $other->min_revenue)
            ->first();

        return $best?->id === $rule->id;
    }

    /**
     * Pasien baru pada periode ini, dihitung per penerima bonus. Pasien baru
     * = kunjungan berbayar PERTAMA seumur hidupnya jatuh di dalam periode;
     * kunjungan berikutnya tidak pernah dihitung lagi.
     *
     * @return array<int, int> user_id => jumlah pasien baru
     */
    private function newPatientCountsByBeneficiary(): array
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
            ->pluck('referred_by', 'id');

        $firstTherapists = Transaction::query()
            ->whereNull('cancelled_at')
            ->whereIn('patient_id', $firstVisits->keys())
            ->get(['patient_id', 'therapist_id', 'issued_at'])
            ->groupBy('patient_id')
            ->map(fn (Collection $group) => $group->sortBy('issued_at')->first()->therapist_id);

        $counts = [];

        foreach ($firstVisits->keys() as $patientId) {
            $beneficiary = $referrers[$patientId] ?? $firstTherapists[$patientId] ?? null;

            if ($beneficiary !== null) {
                $counts[$beneficiary] = ($counts[$beneficiary] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
