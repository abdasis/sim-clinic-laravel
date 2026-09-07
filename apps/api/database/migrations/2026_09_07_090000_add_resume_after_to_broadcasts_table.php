<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sejak kapan campaign yang dijeda boleh dicoba lagi.
 *
 * Tidak semua jeda sama. Gateway yang tersandung boleh dilanjutkan begitu
 * sesinya pulih, tapi WhatsApp yang menahan laju kiriman justru menuntut
 * diam sejenak — melanjutkan lima menit kemudian, seperti jeda biasa, sama
 * saja dengan mengabaikan peringatan yang baru saja diberikan. Null berarti
 * tidak ada masa tunggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->timestamp('resume_after')->nullable()->after('auto_resumes');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn('resume_after');
        });
    }
};
