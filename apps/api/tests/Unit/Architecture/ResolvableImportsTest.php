<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Penjaga terhadap import yang menunjuk kelas yang tidak ada.
 *
 * Lahir dari bug sungguhan: PaymentController meng-import
 * `App\Actions\PayTransactionAction`, padahal kelasnya bernamespace
 * `App\Actions\Transaction`. PHP tidak mengeluh saat berkasnya dimuat —
 * yang meledak baru container, saat endpointnya dipanggil. Endpoint itu
 * kebetulan belum punya test HTTP, jadi setiap pembayaran POS dijawab 500
 * dan semua nota tetap tercatat belum dibayar.
 *
 * Salah ketik namespace tidak boleh lagi menunggu sampai kasir yang
 * menemukannya.
 */
class ResolvableImportsTest extends TestCase
{
    public function test_every_imported_app_class_exists(): void
    {
        $base = dirname(__DIR__, 3);
        $missing = [];

        foreach (['app', 'database', 'routes', 'tests'] as $dir) {
            foreach ($this->phpFiles($base.'/'.$dir) as $file) {
                $source = file_get_contents($file);

                preg_match_all('/^use\s+(App\\\\[A-Za-z0-9_\\\\]+)\s*(?:as\s+\w+\s*)?;/m', $source, $matches);

                foreach ($matches[1] as $class) {
                    // Berkas belum tentu ter-autoload di test unit, jadi yang
                    // diperiksa keberadaan berkasnya sesuai peta PSR-4.
                    $path = $base.'/app/'.str_replace('\\', '/', substr($class, strlen('App\\'))).'.php';

                    if (! is_file($path) && ! class_exists($class) && ! interface_exists($class) && ! trait_exists($class)) {
                        $missing[] = $class.' di '.str_replace($base.'/', '', $file);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missing)),
            'Ada import yang menunjuk kelas tidak ada — akan meledak saat container meresolusinya.',
        );
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
