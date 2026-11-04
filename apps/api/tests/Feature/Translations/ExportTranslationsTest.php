<?php

namespace Tests\Feature\Translations;

use Tests\TestCase;

/**
 * Kamus frontend sekarang dibundel saat build, bukan diambil dari API.
 * Tes ini menjaga dua hal yang dulu pernah bocor: modul baru wajib ikut
 * terekspor, dan endpoint lama tidak boleh hidup lagi.
 */
class ExportTranslationsTest extends TestCase
{
    public function test_command_exports_indonesian_dictionary(): void
    {
        $path = sys_get_temp_dir().'/translations-id-'.uniqid().'.json';

        $this->artisan('translations:export', ['--path' => $path])->assertSuccessful();

        $this->assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);
        $this->assertSame('Broadcast WA', $data['broadcast']['title']);
        $this->assertSame('Staf', $data['staff']['title']);

        foreach (glob(lang_path('id').'/*.php') as $file) {
            $group = basename($file, '.php');

            $this->assertArrayHasKey($group, $data, "Grup terjemahan '{$group}' tidak ikut diekspor.");
        }

        @unlink($path);
    }

    public function test_command_fails_when_target_cannot_be_written(): void
    {
        $this->artisan('translations:export', ['--path' => '/proc/translations-id.json'])
            ->assertFailed();
    }

    public function test_legacy_translations_route_is_gone(): void
    {
        $this->getJson('/api/translations')->assertNotFound();
    }
}
