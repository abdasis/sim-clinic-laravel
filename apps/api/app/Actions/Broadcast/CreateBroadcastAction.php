<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastAudience;
use App\Enums\BroadcastKind;
use App\Models\Broadcast;
use App\Support\BroadcastAudienceBuilder;
use App\Support\ClinicIdentity;
use App\Support\TemplateVariables;
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
        $template = (array) $data['template'];

        $built = app(BroadcastAudienceBuilder::class)->build(
            $audience,
            $params,
            marketing: $kind === BroadcastKind::Promo,
        );

        $broadcast = Broadcast::create([
            'title' => $data['title'],
            'kind' => $kind,
            // Snapshot pesan dirender dari body template supaya layar
            // campaign bisa menampilkan bunyi pesannya tanpa memanggil Meta.
            'message' => TemplateVariables::render(
                (string) $template['body'],
                TemplateVariables::resolve(
                    (array) ($template['params'] ?? []),
                    $this->clinicVariables(),
                ),
            ),
            'template' => $template,
            'image_path' => $this->storeImage($data['image'] ?? null),
            'audience' => $audience,
            'audience_params' => $params,
            'created_by' => Auth::id(),
        ]);

        $clinic = $this->clinicVariables();
        $templateParams = (array) ($template['params'] ?? []);
        $templateBody = (string) $template['body'];
        $now = now();

        $rows = $built['recipients']->map(function (array $recipient) use ($broadcast, $data, $templateParams, $templateBody, $clinic, $now): array {
            $values = TemplateVariables::resolve(
                $templateParams,
                $recipient['variables'] + $clinic,
            );

            return [
                'tenant_id' => $broadcast->tenant_id,
                'broadcast_id' => $broadcast->id,
                'patient_id' => $recipient['patient_id'],
                'reminder_rule_id' => $data['reminder_rule_id'] ?? null,
                'name' => $recipient['name'],
                'phone' => $recipient['phone'],
                'message' => TemplateVariables::render($templateBody, $values),
                // insert() massal tidak melewati cast, jadi dienkode sendiri.
                'template_values' => json_encode($values),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

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
     * @return array<string, string>
     */
    private function clinicVariables(): array
    {
        return [
            'klinik' => ClinicIdentity::displayName(),
            'klinik_telepon' => app('tenant')->phone ?? '',
        ];
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
}
