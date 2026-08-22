<?php

namespace App\Support;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastAudience;
use App\Enums\BroadcastKind;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastReminderSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up otomatis tingkat klinik: satu ambang hari untuk seluruh pasien,
 * dihitung dari rekam medis terakhirnya.
 *
 * Berbeda dari ReminderEngine yang bekerja per-layanan dengan sumber transaksi
 * POS. Yang ini bertanya "kapan terakhir pasien ini ditangani", jawabannya ada
 * di rekam medis — pasien bisa datang tanpa transaksi tercatat, dan transaksi
 * bisa dibuat tanpa tindakan.
 *
 * Hanya menjangkau pasien yang bersedia menerima broadcast; ini pesan ajakan
 * kembali, bukan pengingat operasional.
 *
 * Anti-duplikat: satu pasien hanya dikirimi sekali per periode kunjungan.
 * Penandanya baris penerima dari broadcast otomatis yang dibuat SETELAH
 * tindakan terakhirnya — tindakan baru membuka periode baru.
 */
class AutoReminderEngine
{
    private const DEFAULT_TEMPLATE = "Halo {nama} 👋\n\nSudah {hari_sejak_kunjungan} hari sejak {layanan_terakhir} terakhirmu di {klinik} pada {tanggal_terakhir}.\n\nYuk jadwalkan perawatan berikutnya. Balas pesan ini atau hubungi {klinik_telepon} ya 😊";

    /**
     * @return array{created: int, recipients: int}
     */
    public function run(?Carbon $today = null): array
    {
        $today ??= now();

        $setting = BroadcastReminderSetting::query()->where('is_active', true)->with('template')->first();

        if ($setting === null) {
            return ['created' => 0, 'recipients' => 0];
        }

        $due = $this->duePatients($setting->days, $today);

        if ($due->isEmpty()) {
            return ['created' => 0, 'recipients' => 0];
        }

        $broadcast = $this->buildBroadcast($setting, $due, $today);

        if ($broadcast === null) {
            return ['created' => 0, 'recipients' => 0];
        }

        return ['created' => 1, 'recipients' => $broadcast->recipients()->count()];
    }

    /**
     * Pasien yang tindakan TERAKHIR-nya sudah lewat ambang hari, dan belum
     * dikirimi follow-up untuk periode itu.
     *
     * @return Collection<int, object{patient_id: int, last_at: string, last_service: ?string}>
     */
    private function duePatients(int $days, Carbon $today): Collection
    {
        $tenantId = app('tenant')->id;
        $cutoff = $today->copy()->subDays($days)->endOfDay();

        // Rekam medis terakhir per pasien, beserta nama tindakan pada rekam
        // itu. Rekam yang sudah dihapus tidak dihitung — pasiennya bisa jadi
        // memang belum pernah ditangani.
        $lastRecord = DB::table('medical_records')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->groupBy('patient_id')
            ->selectRaw('patient_id, MAX(created_at) as last_at');

        $rows = DB::table('medical_records')
            ->joinSub($lastRecord, 'last', fn ($join) => $join
                ->on('medical_records.patient_id', '=', 'last.patient_id')
                ->on('medical_records.created_at', '=', 'last.last_at'))
            ->leftJoin('treatment_records', 'treatment_records.medical_record_id', '=', 'medical_records.id')
            ->whereNull('medical_records.deleted_at')
            ->where('last.last_at', '<=', $cutoff)
            ->groupBy('medical_records.patient_id', 'last.last_at')
            ->selectRaw('medical_records.patient_id, last.last_at, MIN(treatment_records.service_name) as last_service')
            ->get();

        // Satu kunjungan cukup satu pengingat, siapa pun pengirimnya —
        // termasuk aturan per layanan yang berjalan setengah jam sebelumnya.
        return $rows->filter(
            fn (object $row) => ! ReminderHistory::remindedSince($row->patient_id, $row->last_at)
        )->values();
    }

    /**
     * @param  Collection<int, object>  $due
     */
    private function buildBroadcast(BroadcastReminderSetting $setting, Collection $due, Carbon $today): ?Broadcast
    {
        // Builder dipakai untuk normalisasi nomor, penyaringan opt-in, dan
        // dedup nomor bersama — variabel kunjungannya diganti karena builder
        // menghitungnya dari transaksi POS, bukan rekam medis.
        $built = (new BroadcastAudienceBuilder)->build(BroadcastAudience::All, [], marketing: true);
        $byPatient = $built['recipients']->keyBy('patient_id');

        $template = $setting->template?->body ?? self::DEFAULT_TEMPLATE;
        $tenant = app('tenant');
        $now = now();
        $rows = [];

        foreach ($due as $record) {
            $recipient = $byPatient[$record->patient_id] ?? null;

            if ($recipient === null) {
                continue;
            }

            $rows[] = [
                'patient_id' => $recipient['patient_id'],
                'name' => $recipient['name'],
                'phone' => $recipient['phone'],
                'message' => strtr($template, $this->replacements([
                    'nama' => $recipient['name'],
                    'layanan_terakhir' => $record->last_service ?: '-',
                    'tanggal_terakhir' => Carbon::parse($record->last_at)->translatedFormat('d F Y'),
                    // Per hari kalender, bukan per 24 jam: tindakan pukul
                    // 15.00 yang diingatkan pukul 08.30 tetap terhitung penuh.
                    'hari_sejak_kunjungan' => (string) Carbon::parse($record->last_at)
                        ->startOfDay()
                        ->diffInDays($today->copy()->startOfDay()),
                    'klinik' => ClinicIdentity::displayName($tenant),
                    'klinik_telepon' => $tenant->phone ?? '',
                ])),
                'status' => BroadcastRecipientStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return null;
        }

        $broadcast = Broadcast::create([
            'title' => 'Follow-up otomatis — '.$today->translatedFormat('d F Y'),
            'kind' => BroadcastKind::Reminder,
            'status' => BroadcastStatus::Ready,
            'message' => $template,
            'audience' => BroadcastAudience::All,
            // Penanda `auto` inilah yang membedakan broadcast ini dari
            // pengingat per-layanan, baik untuk anti-duplikat maupun untuk
            // menentukan mana yang boleh diantrekan command ini.
            'audience_params' => ['auto' => true, 'days' => $setting->days],
        ]);

        $broadcast->recipients()->insert(array_map(
            fn (array $row) => $row + ['tenant_id' => $broadcast->tenant_id, 'broadcast_id' => $broadcast->id],
            $rows,
        ));

        app(LogAuditAction::class)->handle(
            'broadcast.auto_reminder.created',
            $broadcast,
            null,
            ['new' => ['days' => $setting->days, 'recipients' => count($rows)]],
            'Menyusun follow-up otomatis untuk '.count($rows).' pasien yang tindakan terakhirnya sudah lewat '.$setting->days.' hari.',
        );

        return $broadcast;
    }

    /**
     * Variabel diterima dalam bentuk `{nama}` maupun `{{nama}}` — templat
     * lama memakai kurung ganda.
     *
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
                $replacements['{'.$alias.'}'] = $variables[$key];
                $replacements['{{'.$alias.'}}'] = $variables[$key];
            }
        }

        return $replacements;
    }
}
