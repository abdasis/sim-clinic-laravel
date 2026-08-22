<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastAudience;
use App\Enums\BroadcastKind;
use App\Models\Broadcast;
use App\Support\BroadcastAudienceBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Buat broadcast beserta snapshot penerimanya. Pesan dirender sekarang,
 * bukan saat dikirim — daftar pasien boleh berubah setelah ini tanpa
 * mengubah siapa menerima apa.
 */
class CreateBroadcastAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Broadcast
    {
        $audience = BroadcastAudience::from($data['audience']);
        $params = $data['audience_params'] ?? [];
        $kind = BroadcastKind::from($data['kind'] ?? 'promo');

        $built = app(BroadcastAudienceBuilder::class)->build(
            $audience,
            $params,
            marketing: $kind === BroadcastKind::Promo,
        );

        $broadcast = Broadcast::create([
            'title' => $data['title'],
            'kind' => $kind,
            'message' => $data['message'],
            'image_path' => $this->storeImage($data['image'] ?? null),
            'audience' => $audience,
            'audience_params' => $params,
            'created_by' => Auth::id(),
        ]);

        $clinicName = app('tenant')->name;
        $clinicPhone = app('tenant')->phone ?? '';
        $now = now();

        $rows = $built['recipients']->map(fn (array $recipient) => [
            'tenant_id' => $broadcast->tenant_id,
            'broadcast_id' => $broadcast->id,
            'patient_id' => $recipient['patient_id'],
            'reminder_rule_id' => $data['reminder_rule_id'] ?? null,
            'name' => $recipient['name'],
            'phone' => $recipient['phone'],
            'message' => $this->render($data['message'], $recipient['variables'] + ['klinik' => $clinicName, 'klinik_telepon' => $clinicPhone]),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // Satu insert massal; broadcast klinik bisa ratusan baris.
        $broadcast->recipients()->insert($rows);

        app(LogAuditAction::class)->handle(
            'broadcast.created',
            $broadcast,
            Auth::user(),
            [
                'attributes' => $broadcast->getAttributes(),
                'recipients_count' => count($rows),
                'without_phone' => $built['without_phone'],
            ],
            'Membuat broadcast '.$broadcast->title.' untuk '.count($rows).' penerima.',
        );

        return $broadcast;
    }

    /**
     * Simpan poster broadcast di folder milik kliniknya sendiri.
     *
     * Disk `public` sama dengan foto rekam medis: layar campaign perlu
     * menampilkannya kembali, dan berkasnya memang tidak rahasia.
     */
    private function storeImage(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        return $file->store('broadcast-images/'.app('tenant')->id, 'public');
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function render(string $template, array $variables): string
    {
        // Nama panjang gaya {{nama_pasien}} diterima sebagai alias supaya
        // templat yang ditulis dengan gaya itu tetap terisi.
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

        return strtr($template, $replacements);
    }
}
