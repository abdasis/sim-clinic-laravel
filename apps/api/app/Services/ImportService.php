<?php

namespace App\Services;

use App\Actions\Import\DownloadImportTemplateAction;
use App\Actions\Import\ImportProductsAction;
use App\Actions\Import\ImportServicesAction;
use App\Actions\Import\ParseImportFileAction;
use App\Actions\LogAuditAction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Impor massal katalog dari berkas template.
 *
 * Berkas dibaca dan diperiksa di luar transaksi: berkas yang tidak bisa
 * dibaca atau header-nya salah harus ditolak sebelum apa pun tersentuh.
 * Baru setelah itu barisnya ditulis dalam satu transaksi.
 */
class ImportService
{
    /**
     * Batas baris per unggahan.
     *
     * Impor berjalan di dalam permintaan HTTP, satu transaksi basis data, dan
     * satu proses PHP. Berkas 2 MB masih bisa berisi puluhan ribu baris —
     * cukup untuk menembus batas waktu server, dan yang terjadi kemudian
     * bukan pesan galat melainkan koneksi yang putus dengan transaksi
     * menggantung di tengah jalan.
     *
     * Angkanya dipilih supaya katalog klinik terbesar sekalipun muat dalam
     * sekali unggah, sementara berkas yang jauh di atas itu hampir pasti
     * salah kirim.
     *
     * ponytail: batas, bukan antrean. Ceiling-nya di situ — klinik yang
     * benar-benar perlu memasukkan lebih dari seribu baris harus memecah
     * berkasnya. Upgrade path-nya memindahkan impor ke job dengan halaman
     * status, yang butuh perubahan di sisi tampilan juga.
     */
    private const MAX_ROWS = 1000;

    /**
     * @return array{imported: int, updated: int, errors: array<int, array<string, mixed>>}
     */
    public function importServices(UploadedFile $file, User $actor): array
    {
        $parsed = app(ParseImportFileAction::class)->handle($file, ImportServicesAction::HEADER);
        $this->guardRowCount($parsed['rows']);

        return $this->run(
            fn () => app(ImportServicesAction::class)->handle($parsed['rows']),
            'service.import',
            'layanan',
            $file,
            $actor,
        );
    }

    /**
     * @return array{imported: int, updated: int, errors: array<int, array<string, mixed>>}
     */
    public function importProducts(UploadedFile $file, User $actor): array
    {
        $parsed = app(ParseImportFileAction::class)->handle($file, ImportProductsAction::HEADER);
        $this->guardRowCount($parsed['rows']);

        return $this->run(
            fn () => app(ImportProductsAction::class)->handle($parsed['rows']),
            'product.import',
            'produk',
            $file,
            $actor,
        );
    }

    /**
     * Tolak berkas yang terlalu panjang sebelum satu baris pun ditulis.
     *
     * @param  Collection<int, mixed>|array<int, mixed>  $rows
     */
    private function guardRowCount(Collection|array $rows): void
    {
        $count = is_array($rows) ? count($rows) : $rows->count();

        if ($count > self::MAX_ROWS) {
            abort(422, __('import.too_many_rows', [
                'max' => self::MAX_ROWS,
                'count' => $count,
            ]));
        }
    }

    public function template(string $type): Spreadsheet
    {
        return app(DownloadImportTemplateAction::class)->handle($type);
    }

    /**
     * @param  callable(): array{imported: int, updated: int, errors: array<int, array<string, mixed>>}  $import
     * @return array{imported: int, updated: int, errors: array<int, array<string, mixed>>}
     */
    private function run(callable $import, string $event, string $noun, UploadedFile $file, User $actor): array
    {
        try {
            return DB::transaction(function () use ($import, $event, $noun, $file, $actor): array {
                $result = $import();

                app(LogAuditAction::class)->handle(
                    $event,
                    null,
                    $actor,
                    [
                        'file' => $file->getClientOriginalName(),
                        'new' => [
                            'imported' => $result['imported'],
                            'updated' => $result['updated'],
                            'errors' => count($result['errors']),
                        ],
                    ],
                    'Mengimpor '.$noun.' dari berkas '.$file->getClientOriginalName().': '
                        .$result['imported'].' baru, '.$result['updated'].' diperbarui, '
                        .count($result['errors']).' baris bermasalah.',
                );

                return $result;
            });
        } catch (\Throwable $e) {
            Log::error('Impor '.$noun.' gagal', [
                'exception' => $e,
                'file' => $file->getClientOriginalName(),
            ]);

            throw $e;
        }
    }
}
