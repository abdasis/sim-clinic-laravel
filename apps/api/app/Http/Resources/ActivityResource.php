<?php

namespace App\Http\Resources;

use App\Support\ActivityLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Satu baris audit log untuk halaman Log Aktivitas.
 *
 * Diff-nya diratakan jadi daftar {field, old, new} supaya frontend cukup
 * punya satu cara menggambar perubahan — baik untuk create (old kosong),
 * update (dua sisi terisi), maupun delete (new kosong).
 */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $diff = $this->diff();
        $changes = $this->changes($diff['old'], $diff['new']);
        $context = $this->context();

        return [
            'id' => $this->id,
            'logged_at' => $this->created_at?->toIso8601String(),
            'module' => $this->log_name,
            'module_label' => ActivityLabel::module($this->log_name),
            'event' => $this->event,
            'event_label' => ActivityLabel::event($this->event),
            'description' => $this->description,
            'causer_id' => $this->causer_id,
            'causer_name' => $this->causer?->name,
            'causer_email' => $this->causer?->email,
            'causer_role' => $this->causer?->clinic_role?->value,
            'subject_id' => $this->subject_id,
            'subject_type' => $this->subject_type,
            'subject_type_label' => ActivityLabel::subjectType($this->subject_type),
            // subject null padahal subject_type terisi berarti barisnya sudah
            // hilang — dibedakan dari aktivitas yang memang tanpa objek.
            'subject_name' => ActivityLabel::subjectName($this->subject),
            'subject_missing' => $this->subject_type !== null && $this->subject === null,
            'changes' => $changes,
            'context' => $context,
            'has_details' => $changes !== [] || $context !== [],
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<int, array{field: string, label: string, old: mixed, new: mixed}>
     */
    private function changes(array $old, array $new): array
    {
        $fields = array_values(array_unique([...array_keys($old), ...array_keys($new)]));

        sort($fields);

        return array_map(fn (string $field) => [
            'field' => $field,
            'label' => Str::headline($field),
            'old' => $old[$field] ?? null,
            'new' => $new[$field] ?? null,
        ], $fields);
    }
}
