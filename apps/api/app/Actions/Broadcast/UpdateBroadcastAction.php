<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastAudience;
use App\Enums\BroadcastKind;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Support\BroadcastAudienceBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Sunting broadcast yang belum mulai dikirim. Seluruh penerima dibangun ulang
 * dari sasaran baru; aman karena belum satu pun pesan berangkat.
 */
class UpdateBroadcastAction
{
    /** Kunci audience_params yang berasal dari formulir. */
    private const FORM_PARAMS = ['days', 'service_id', 'patient_ids'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Broadcast $broadcast, array $data): Broadcast
    {
        $this->guardEditable($broadcast);

        $oldTemplate = (array) ($broadcast->template ?? []);
        $old = [
            'title' => $broadcast->title,
            'audience' => $broadcast->audience?->value,
            'template' => [
                'name' => $oldTemplate['name'] ?? null,
                'language' => $oldTemplate['language'] ?? null,
            ],
            'image_path' => $broadcast->image_path,
            'recipients_count' => $broadcast->recipients()->count(),
        ];

        $audience = BroadcastAudience::from($data['audience']);
        $params = $this->mergeAudienceParams(
            (array) ($broadcast->audience_params ?? []),
            (array) ($data['audience_params'] ?? []),
        );
        $template = (array) $data['template'];
        $templateBody = (string) $template['body'];
        $templateParams = (array) ($template['params'] ?? []);

        $built = app(BroadcastAudienceBuilder::class)->build(
            $audience,
            $params,
            marketing: $broadcast->kind === BroadcastKind::Promo,
        );

        $imagePath = $this->resolveImagePath($broadcast, $data);
        $reminderRuleId = $this->existingReminderRuleId($broadcast);

        $broadcast->update([
            'title' => $data['title'],
            'message' => app(BuildBroadcastRecipientsAction::class)->snapshot($templateBody, $templateParams),
            'template' => $template,
            'image_path' => $imagePath,
            'audience' => $audience,
            'audience_params' => $params,
        ]);

        // Belum ada pesan terkirim, jadi seluruh baris lama aman diganti.
        $broadcast->recipients()->delete();

        $rows = app(BuildBroadcastRecipientsAction::class)->handle(
            $broadcast,
            $built['recipients'],
            $templateBody,
            $templateParams,
            $reminderRuleId,
        );

        $broadcast->recipients()->insert($rows);

        // Berkas lama baru dibuang setelah baris baru tersimpan, supaya
        // kegagalan di tengah jalan tidak meninggalkan broadcast tanpa poster.
        if ($old['image_path'] !== null && $old['image_path'] !== $imagePath) {
            Storage::disk('public')->delete($old['image_path']);
        }

        app(LogAuditAction::class)->handle(
            'broadcast.updated',
            $broadcast,
            Auth::user(),
            [
                'old' => $old,
                'new' => [
                    'title' => $data['title'],
                    'audience' => $audience->value,
                    'template' => [
                        'name' => $template['name'] ?? null,
                        'language' => $template['language'] ?? null,
                    ],
                    'image_path' => $imagePath,
                    'recipients_count' => count($rows),
                ],
            ],
            'Memperbarui broadcast '.$data['title'].' — penerima diganti dari '
                .$old['recipients_count'].' menjadi '.count($rows).' orang.',
        );

        return $broadcast;
    }

    /**
     * Yang boleh disunting hanya broadcast yang belum mulai dikirim. Penerima
     * berstatus lain menandakan pengiriman sudah menyentuh kampanye ini walau
     * statusnya masih tertinggal.
     */
    private function guardEditable(Broadcast $broadcast): void
    {
        abort_unless($broadcast->status === BroadcastStatus::Ready, 422, __('broadcast.edit_locked'));

        abort_if(
            $broadcast->recipients()->where('status', '!=', BroadcastRecipientStatus::Pending)->exists(),
            422,
            __('broadcast.edit_locked'),
        );
    }

    /**
     * Kunci di luar formulir (mis. `auto`, `reminder_rule_id`) tetap
     * dipertahankan; kunci formulir mengikuti input supaya sasaran lama tidak
     * tertinggal saat audiensnya diganti.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private function mergeAudienceParams(array $old, array $new): array
    {
        foreach (self::FORM_PARAMS as $key) {
            unset($old[$key]);
        }

        return $old + array_intersect_key($new, array_flip(self::FORM_PARAMS));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveImagePath(Broadcast $broadcast, array $data): ?string
    {
        $file = $data['image'] ?? null;

        if ($file instanceof UploadedFile) {
            return $file->store('broadcast-images/'.app('tenant')->id, 'public');
        }

        // Tanpa berkas baru dan tanpa permintaan hapus, poster lama menempel
        // apa adanya.
        return $this->wantsImageRemoved($data) ? null : $broadcast->image_path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function wantsImageRemoved(array $data): bool
    {
        return filter_var($data['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function existingReminderRuleId(Broadcast $broadcast): ?int
    {
        $id = $broadcast->recipients()->whereNotNull('reminder_rule_id')->value('reminder_rule_id');

        return $id === null ? null : (int) $id;
    }
}
