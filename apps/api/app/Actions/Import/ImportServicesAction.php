<?php

namespace App\Actions\Import;

use App\Enums\CategorizableType;
use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * Tulis baris layanan dari berkas impor.
 *
 * Kesalahan satu baris tidak menggugurkan baris lain: pengisi template
 * biasanya keliru di beberapa baris saja, dan menolak seluruh berkas berarti
 * ia harus mengulang semuanya dari awal.
 *
 * Menyimpan lewat model langsung, bukan lewat Create/UpdateServiceAction:
 * untuk ratusan baris, satu catatan audit ringkas lebih terbaca daripada
 * ratusan catatan yang sama.
 *
 * ponytail: upsert model langsung di impor; audit lewat satu log ringkasan.
 */
class ImportServicesAction
{
    public const HEADER = ['nama', 'kategori', 'deskripsi', 'harga', 'durasi_menit', 'status'];

    use ResolvesImportCategory;

    /**
     * @param  Collection<int, array{line: int, values: array<string, string>}>  $rows
     * @return array{imported: int, updated: int, errors: array<int, array{row: int, field: string, message: string}>}
     */
    public function handle(Collection $rows): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $row) {
            $validator = Validator::make($row['values'], [
                'nama' => ['required', 'string', 'max:255'],
                'harga' => ['required', 'numeric', 'gte:0'],
                'durasi_menit' => ['required', 'integer', 'gte:1', 'max:600'],
                'status' => ['nullable', 'in:active,archived'],
                'kategori' => ['nullable', 'string', 'max:100'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors[] = ['row' => $row['line'], 'field' => $field, 'message' => $messages[0]];
                }

                continue;
            }

            $values = $validator->validated();

            $service = Service::query()->firstOrNew(['name' => $values['nama']]);
            $existed = $service->exists;

            $service->fill([
                'description' => $row['values']['deskripsi'] ?: null,
                'price' => $values['harga'],
                'duration_minutes' => $values['durasi_menit'],
                'status' => $values['status'] ?: ServiceStatus::Active->value,
            ])->save();

            $service->syncCategory($this->categoryId($values['kategori'] ?? '', CategorizableType::Service));

            $existed ? $updated++ : $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated, 'errors' => $errors];
    }
}
