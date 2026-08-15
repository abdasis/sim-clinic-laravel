<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Enums\BroadcastAudience;
use App\Models\Broadcast;
use App\Support\BroadcastAudienceBuilder;
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

        $built = app(BroadcastAudienceBuilder::class)->build($audience, $params);

        $broadcast = Broadcast::create([
            'title' => $data['title'],
            'message' => $data['message'],
            'audience' => $audience,
            'audience_params' => $params,
            'created_by' => Auth::id(),
        ]);

        $clinicName = app('tenant')->name;
        $now = now();

        $rows = $built['recipients']->map(fn (array $recipient) => [
            'tenant_id' => $broadcast->tenant_id,
            'broadcast_id' => $broadcast->id,
            'patient_id' => $recipient['patient_id'],
            'name' => $recipient['name'],
            'phone' => $recipient['phone'],
            'message' => $this->render($data['message'], $recipient['variables'] + ['klinik' => $clinicName]),
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
     * @param  array<string, string>  $variables
     */
    private function render(string $template, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = $value;
        }

        return strtr($template, $replacements);
    }
}
