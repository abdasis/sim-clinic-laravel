<?php

namespace App\Actions\Broadcast;

use App\Models\Broadcast;
use App\Support\TemplateVariables;
use Illuminate\Support\Collection;

/**
 * Susun baris penerima beserta pesan yang sudah dirender. Dipakai pembuatan
 * dan penyuntingan broadcast supaya isi pesan per penerima tidak pernah
 * berbeda antar kedua jalur.
 */
class BuildBroadcastRecipientsAction
{
    /**
     * @param  Collection<int, array<string, mixed>>  $recipients
     * @param  array<int, string>  $templateParams
     * @return array<int, array<string, mixed>>
     */
    public function handle(
        Broadcast $broadcast,
        Collection $recipients,
        string $templateBody,
        array $templateParams,
        ?int $reminderRuleId = null,
    ): array {
        $now = now();

        return $recipients->map(function (array $recipient) use ($broadcast, $templateBody, $templateParams, $reminderRuleId, $now): array {
            $values = TemplateVariables::resolve(
                $templateParams,
                $recipient['variables'],
            );

            return [
                'tenant_id' => $broadcast->tenant_id,
                'broadcast_id' => $broadcast->id,
                'patient_id' => $recipient['patient_id'],
                'reminder_rule_id' => $reminderRuleId,
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
    }

    /**
     * Snapshot teks pesan broadcast itu sendiri, dirender sekali dari body
     * template supaya layar campaign bisa menampilkannya tanpa memanggil Meta.
     *
     * @param  array<int, string>  $templateParams
     */
    public function snapshot(string $templateBody, array $templateParams): string
    {
        return TemplateVariables::render(
            $templateBody,
            TemplateVariables::resolve($templateParams, []),
        );
    }
}
