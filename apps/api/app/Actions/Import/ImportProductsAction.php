<?php

namespace App\Actions\Import;

use App\Enums\CategorizableType;
use App\Enums\ServiceStatus;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * Tulis baris produk dari berkas impor.
 *
 * Saldo stok sengaja tidak ada di template dan tidak pernah disentuh di sini:
 * stok hanya tumbuh lewat mutasi supaya riwayatnya selalu bisa ditelusuri.
 *
 * ponytail: upsert model langsung di impor; audit lewat satu log ringkasan.
 */
class ImportProductsAction
{
    use ResolvesImportCategory;

    public const HEADER = ['nama', 'kategori', 'satuan', 'stok_minimum', 'harga', 'status'];

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
                'satuan' => ['required', 'string', 'max:50'],
                'stok_minimum' => ['required', 'integer', 'gte:0'],
                'harga' => ['required', 'numeric', 'gte:0'],
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

            $product = Product::query()->firstOrNew(['name' => $values['nama']]);
            $existed = $product->exists;

            $product->fill([
                // Kolom string tetap diisi selama kolomnya belum dibuang,
                // supaya migrasi FK masih bisa dibatalkan tanpa kehilangan
                // satuan yang baru saja diimpor.
                'unit' => $values['satuan'],
                'unit_id' => $this->unitId($values['satuan']),
                'min_threshold' => $values['stok_minimum'],
                'price' => $values['harga'],
                'status' => $values['status'] ?: ServiceStatus::Active->value,
            ])->save();

            $product->syncCategory($this->categoryId($values['kategori'] ?? '', CategorizableType::Product));

            $existed ? $updated++ : $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Satuan dari berkas impor tetap ditulis sebagai teks oleh pengguna, jadi
     * yang belum ada dibuatkan barisnya. Nama dinormalkan agar "Botol" dan
     * "botol " tidak beranak jadi dua satuan berbeda.
     *
     * ponytail: resolusi inline karena hanya impor yang membutuhkannya;
     * angkat jadi trait saat ada pemanggil kedua.
     */
    private function unitId(?string $name): ?int
    {
        $name = mb_strtolower(trim((string) $name));

        if ($name === '') {
            return null;
        }

        return Unit::firstOrCreate(['name' => $name], ['status' => ServiceStatus::Active->value])->id;
    }
}
