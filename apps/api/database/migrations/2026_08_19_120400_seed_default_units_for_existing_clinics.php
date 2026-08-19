<?php

use App\Actions\Tenant\SeedDefaultUnitsAction;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Klinik yang sudah berjalan ikut menerima paket satuan bawaan.
 *
 * Yang dijalankan Action-nya, bukan salinan daftarnya, supaya isi paket
 * bawaan hanya hidup di satu tempat. Action-nya idempoten dan melewati
 * klinik yang satuannya sudah terbentuk dari migrasi data sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $action = app(SeedDefaultUnitsAction::class);

        Tenant::query()->orderBy('id')->each(fn (Tenant $tenant) => $action->handle($tenant->id));
    }

    public function down(): void
    {
        // Satuan bawaan bisa saja sudah dipakai produk; menghapusnya balik
        // justru memutus tautan yang sah.
    }
};
