<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kunci mentah seperti "expense.title" pernah bocor ke layar karena daftar
 * grup di controller tertinggal modul baru. Tes ini menjamin setiap berkas
 * lang otomatis ikut terkirim — modul baru tidak bisa lupa didaftarkan.
 */
class TranslationsTest extends TestCase
{
    public function test_every_lang_file_is_served(): void
    {
        $response = $this->getJson('/api/translations')->assertOk();
        $served = array_keys($response->json('data'));

        foreach (glob(lang_path('id').'/*.php') as $file) {
            $group = basename($file, '.php');

            $this->assertContains($group, $served, "Grup terjemahan '{$group}' tidak ikut terkirim ke frontend.");
        }
    }

    public function test_new_module_groups_have_indonesian_values(): void
    {
        $data = $this->getJson('/api/translations')->json('data');

        // Key yang dilaporkan tampil mentah di browser.
        $this->assertSame('Pengeluaran', $data['expense']['title']);
        $this->assertSame('Ringkasan Pengeluaran', $data['expense']['summary'] ?? null);
        $this->assertSame('Promosi', $data['promo']['title']);
        $this->assertSame('Profil Perusahaan', $data['company_profile']['title']);
        $this->assertNotEmpty($data['commission']['title'] ?? null);
        $this->assertNotEmpty($data['broadcast']['title'] ?? null);
        $this->assertNotEmpty($data['commission']['preview'] ?? null);
    }
}
