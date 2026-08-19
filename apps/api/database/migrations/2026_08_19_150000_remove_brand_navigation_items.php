<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bagian sub-brand dibuang dari halaman publik, jadi tautan yang menuju
 * jangkarnya harus ikut hilang. Kalau dibiarkan, kaki halaman klinik yang
 * sudah terlanjur di-seed tetap memajang tautan ke bagian yang tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_navigation_items')->where('url', '#brand')->delete();
    }

    public function down(): void
    {
        // Tautannya bisa dibuat lagi lewat CMS; tidak ada yang perlu dipulihkan
        // di sini karena bagian yang ditujunya memang sudah tidak ada.
    }
};
