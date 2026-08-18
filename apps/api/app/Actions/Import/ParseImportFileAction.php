<?php

namespace App\Actions\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Baca berkas impor jadi baris siap olah.
 *
 * Dua hal ditolak di sini, sebelum apa pun tersimpan: berkas yang tidak bisa
 * dibaca dan header yang tidak cocok. Keduanya berarti seluruh isinya tidak
 * bisa dipercaya, jadi tidak ada gunanya memproses sebagian.
 */
class ParseImportFileAction
{
    /** Batas praktis untuk klinik; di atas ini impor pantas jadi antrean. */
    public const MAX_ROWS = 500;

    /**
     * @param  array<int, string>  $expectedHeader
     * @return array{header: array<int, string>, rows: Collection<int, array<string, string>>}
     */
    public function handle(UploadedFile $file, array $expectedHeader): array
    {
        $table = $this->read($file);

        $header = array_map(
            fn ($value) => mb_strtolower(trim((string) $value)),
            array_shift($table) ?? [],
        );

        $this->assertHeaderMatches($header, $expectedHeader);

        $rows = collect($table)
            // Baris kosong lahir dari spreadsheet yang barisnya pernah
            // disentuh lalu dikosongkan lagi; itu bukan kesalahan pengisi.
            ->reject(fn (array $row) => $this->isBlank($row))
            ->values();

        if ($rows->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => __('import.too_many_rows', ['max' => self::MAX_ROWS]),
            ]);
        }

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => __('import.no_rows')]);
        }

        return [
            'header' => $header,
            // Nomor baris di spreadsheet: +2 karena header ada di baris 1.
            'rows' => $rows->map(fn (array $row, int $index) => [
                'line' => $index + 2,
                'values' => $this->associate($header, $row),
            ]),
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function read(UploadedFile $file): array
    {
        try {
            return IOFactory::load($file->getRealPath())->getActiveSheet()->toArray();
        } catch (\Throwable $e) {
            Log::error('Berkas impor tidak bisa dibaca', [
                'exception' => $e,
                'file' => $file->getClientOriginalName(),
            ]);

            throw ValidationException::withMessages(['file' => __('import.unreadable')]);
        }
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $expected
     */
    private function assertHeaderMatches(array $header, array $expected): void
    {
        $missing = array_diff($expected, $header);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => __('import.header_invalid', ['columns' => implode(', ', $missing)]),
            ]);
        }
    }

    /** @param  array<int, mixed>  $row */
    private function isBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, mixed>  $row
     * @return array<string, string>
     */
    private function associate(array $header, array $row): array
    {
        $values = [];

        foreach ($header as $index => $column) {
            $values[$column] = trim((string) ($row[$index] ?? ''));
        }

        return $values;
    }
}
