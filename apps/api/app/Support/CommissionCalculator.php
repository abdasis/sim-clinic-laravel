<?php

namespace App\Support;

use App\Enums\CommissionRuleType;
use App\Models\CommissionRule;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Hitung fee terapis untuk satu periode berdasarkan aturan yang tersimpan.
 *
 * Perhitungan sengaja dipisah dari pencatatan: hasilnya ditinjau dulu oleh
 * admin, baru dibukukan sebagai pengeluaran. Angka fee tidak pernah muncul
 * di buku kas tanpa ada yang menyetujuinya.
 *
 * Satuan "pasien" di sini adalah satu kunjungan berbayar (satu transaksi),
 * mengikuti cara klinik menghitung di laporan bulanannya: tiap baris pasien
 * mendapat fee, termasuk bila orangnya datang dua kali dalam sebulan.
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

        $firstVisitAt = $this->firstVisitDates($transactions->pluck('patient_id')->filter()->unique());

        $rows = $transactions
            ->groupBy('therapist_id')
            ->map(fn (Collection $group) => $this->rowFor($group, $rules, $firstVisitAt))
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
     * @param  Collection<int, Transaction>  $group
     * @param  Collection<int, CommissionRule>  $rules
     * @param  Collection<int, string>  $firstVisitAt
     * @return array<string, mixed>
     */
    private function rowFor(Collection $group, Collection $rules, Collection $firstVisitAt): array
    {
        $therapist = $group->first()->therapist;
        $revenue = (float) $group->sum(fn (Transaction $t) => (float) $t->subtotal);
        $visits = $group->count();

        // Pasien baru: kunjungan berbayar pertamanya jatuh di dalam periode ini.
        $newPatients = $group
            ->filter(function (Transaction $t) use ($firstVisitAt) {
                $first = $firstVisitAt[$t->patient_id] ?? null;

                return $first !== null && $first >= $this->from && $first <= $this->to
                    && $t->issued_at?->toDateString() === $first;
            })
            ->pluck('patient_id')
            ->unique()
            ->count();

        $lines = [];

        foreach ($rules as $rule) {
            $line = $this->lineFor($rule, $rules, $revenue, $visits, $newPatients);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return [
            'therapist_id' => $therapist?->id,
            'therapist_name' => $therapist?->name,
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
     * Tanggal kunjungan berbayar pertama tiap pasien, dihitung sekali untuk
     * seluruh periode agar tidak menembak satu kueri per transaksi.
     *
     * @param  Collection<int, int>  $patientIds
     * @return Collection<int, string>
     */
    private function firstVisitDates(Collection $patientIds): Collection
    {
        if ($patientIds->isEmpty()) {
            return collect();
        }

        return Transaction::query()
            ->whereNull('cancelled_at')
            ->whereIn('patient_id', $patientIds)
            ->selectRaw('patient_id, MIN(issued_at) as first_at')
            ->groupBy('patient_id')
            ->pluck('first_at', 'patient_id')
            ->map(fn ($value) => substr((string) $value, 0, 10));
    }
}
