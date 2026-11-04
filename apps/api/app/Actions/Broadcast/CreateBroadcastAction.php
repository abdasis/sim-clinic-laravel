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
        $template = (array) $data['template'];
        $templateBody = (string) $template['body'];
        $templateParams = array_values((array) ($template['params'] ?? []));

        $built = app(BroadcastAudienceBuilder::class)->build(
            $audience,
            $params,
            marketing: $kind === BroadcastKind::Promo,
        );

        $recipients = app(BuildBroadcastRecipientsAction::class);

        $broadcast = Broadcast::create([
            'title' => $data['title'],
            'kind' => $kind,
            // Snapshot pesan dirender dari body template supaya layar
            // campaign bisa menampilkan bunyi pesannya tanpa memanggil Meta.
            'message' => $recipients->snapshot($templateBody, $templateParams),
            // `params` selalu ada walau kosong: layar campaign membacanya
            // untuk memetakan slot, dan kunci yang hilang membuatnya gagal.
            'template' => ['body' => $templateBody, 'params' => $templateParams],
            'image_path' => $this->storeImage($data['image'] ?? null),
            'audience' => $audience,
            'audience_params' => $params,
            'created_by' => Auth::id(),
        ]);

        $rows = $recipients->handle(
            $broadcast,
            $built['recipients'],
            $templateBody,
            $templateParams,
            $data['reminder_rule_id'] ?? null,
        );

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
}
