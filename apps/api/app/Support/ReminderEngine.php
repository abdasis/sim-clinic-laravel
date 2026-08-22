<?php

namespace App\Support;

use App\Enums\BroadcastAudience;
use App\Enums\BroadcastKind;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\ReminderRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cari pasien yang sudah waktunya diingatkan perawatan ulang, lalu susun
 * broadcast pengingat harian.
 *
 * Anti-duplikat: satu pasien + satu aturan + satu kunjungan hanya melahirkan
 * satu pengingat. Kuncinya, pengingat dianggap sudah terkirim bila ada baris
 * penerima untuk pasangan (pasien, aturan) yang dibuat SETELAH kunjungan
 * terakhirnya — kunjungan baru membuka periode pengingat baru.
 */
class ReminderEngine
{
    private const DEFAULT_TEMPLATE = "Halo {nama} 👋\n\nSudah waktunya untuk {layanan_terakhir} kembali di {klinik} ✨\nTerakhir kali perawatanmu {tanggal_terakhir}.\n\nYuk, jadwalkan kunjungan berikutnya. Balas pesan ini atau hubungi {klinik_telepon}.";

    /**
     * @return array{created: int, recipients: int}
     */
    public function run(?Carbon $today = null): array
    {
        $today ??= now();
        $created = 0;
        $totalRecipients = 0;

        $rules = ReminderRule::query()->where('is_active', true)->with(['service', 'template'])->get();

        foreach ($rules as $rule) {
            $due = $this->duePatients($rule, $today);

            if ($due->isEmpty()) {
                continue;
            }

            $broadcast = $this->buildBroadcast($rule, $due, $today);

            if ($broadcast !== null) {
                $created++;
                $totalRecipients += $broadcast->recipients()->count();
            }
        }

        return ['created' => $created, 'recipients' => $totalRecipients];
    }

    /**
     * Pasien yang kunjungan TERAKHIR-nya memuat layanan aturan ini dan sudah
     * lewat ambang harinya.
     *
     * @return Collection<int, object{patient_id: int, last_at: string}>
     */
    private function duePatients(ReminderRule $rule, Carbon $today): Collection
    {
        $tenantId = app('tenant')->id;
        $cutoff = $today->copy()->subDays($rule->days_after);

        $lastVisitWithService = DB::table('transactions')
            ->join('transaction_items', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.tenant_id', $tenantId)
            ->whereNull('transactions.cancelled_at')
            ->where('transaction_items.service_id', $rule->service_id)
            ->groupBy('transactions.patient_id')
            ->selectRaw('transactions.patient_id, MAX(transactions.issued_at) as last_at')
            ->havingRaw('MAX(transactions.issued_at) <= ?', [$cutoff->endOfDay()])
            ->get();

        // Satu kunjungan cukup satu pengingat, siapa pun pengirimnya —
        // termasuk follow-up otomatis yang berjalan setengah jam kemudian.
        return $lastVisitWithService->filter(
            fn (object $visit) => ! ReminderHistory::remindedSince($visit->patient_id, $visit->last_at)
        )->values();
    }

    /**
     * @param  Collection<int, object>  $due
     */
    private function buildBroadcast(ReminderRule $rule, Collection $due, Carbon $today): ?Broadcast
    {
        $builder = new BroadcastAudienceBuilder;

        // Pengingat operasional: opt-in promosi tidak menghalangi, tapi nomor
        // tetap harus valid dan tidak dobel.
        $built = $builder->build(BroadcastAudience::All, [], marketing: false);
        $byPatient = $built['recipients']->keyBy('patient_id');

        $template = $rule->template?->body ?? self::DEFAULT_TEMPLATE;
        $clinicName = ClinicIdentity::displayName();
        $clinicPhone = app('tenant')->phone ?? '';
        $now = now();

        $rows = [];

        foreach ($due as $visit) {
            $recipient = $byPatient[$visit->patient_id] ?? null;

            if ($recipient === null) {
                continue;
            }

            $variables = $recipient['variables'] + [
                'klinik' => $clinicName,
                'klinik_telepon' => $clinicPhone,
            ];

            $rows[] = [
                'patient_id' => $recipient['patient_id'],
                'reminder_rule_id' => $rule->id,
                'name' => $recipient['name'],
                'phone' => $recipient['phone'],
                'message' => strtr($template, $this->replacements($variables)),
                'status' => BroadcastRecipientStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return null;
        }

        $broadcast = Broadcast::create([
            'title' => 'Pengingat '.$rule->service->name.' — '.$today->translatedFormat('d F Y'),
            'kind' => BroadcastKind::Reminder,
            'status' => BroadcastStatus::Ready,
            'message' => $template,
            'audience' => BroadcastAudience::Service,
            'audience_params' => ['service_id' => $rule->service_id, 'reminder_rule_id' => $rule->id],
        ]);

        $broadcast->recipients()->insert(array_map(
            fn (array $row) => $row + ['tenant_id' => $broadcast->tenant_id, 'broadcast_id' => $broadcast->id],
            $rows,
        ));

        return $broadcast;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    private function replacements(array $variables): array
    {
        $aliases = [
            'nama_pasien' => 'nama',
            'nama_treatment' => 'layanan_terakhir',
            'tanggal_treatment' => 'tanggal_terakhir',
            'nomor_clinic' => 'klinik_telepon',
        ];

        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = $value;
            $replacements['{{'.$key.'}}'] = $value;
        }

        foreach ($aliases as $alias => $key) {
            if (isset($variables[$key])) {
                $replacements['{{'.$alias.'}}'] = $variables[$key];
                $replacements['{'.$alias.'}'] = $variables[$key];
            }
        }

        return $replacements;
    }
}
