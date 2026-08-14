<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom morph `related` dibuat manual tanpa index, sehingga menelusuri mutasi
 * stok dari sebuah transaksi harus memindai seluruh tabel. `nullableMorphs`
 * memberi kolom yang sama plus index gabungannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nama kelas penuh diganti alias morph map sebelum kolomnya dibuat
        // ulang, supaya baris lama tetap bisa ditemukan lewat peta.
        $this->rewriteToAliases();

        $existing = DB::table('stock_movements')
            ->whereNotNull('related_type')
            ->get(['id', 'related_type', 'related_id']);

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['related_type', 'related_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->nullableMorphs('related');
        });

        foreach ($existing as $row) {
            DB::table('stock_movements')->where('id', $row->id)->update([
                'related_type' => $row->related_type,
                'related_id' => $row->related_id,
            ]);
        }
    }

    public function down(): void
    {
        $existing = DB::table('stock_movements')
            ->whereNotNull('related_type')
            ->get(['id', 'related_type', 'related_id']);

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropMorphs('related');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
        });

        foreach ($existing as $row) {
            DB::table('stock_movements')->where('id', $row->id)->update([
                'related_type' => $row->related_type,
                'related_id' => $row->related_id,
            ]);
        }
    }

    /**
     * Kolom morph lain sudah lebih dulu menyimpan nama kelas penuh; peta
     * morph kini ditegakkan, jadi baris lama disamakan sekalian.
     */
    private function rewriteToAliases(): void
    {
        $columns = [
            'stock_movements' => ['related_type'],
            'audit_logs' => ['subject_type', 'causer_type'],
        ];

        foreach ($columns as $table => $morphColumns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($morphColumns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach (Relation::morphMap() as $alias => $class) {
                    DB::table($table)
                        ->where($column, $class)
                        ->update([$column => $alias]);
                }
            }
        }
    }
};
