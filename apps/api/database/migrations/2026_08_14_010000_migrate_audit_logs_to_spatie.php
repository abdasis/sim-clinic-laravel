<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptasi tabel audit_logs ke skema spatie/laravel-activitylog
 * (docs/erd/audit_logs.md). Pemetaan:
 * - action        -> event (kode aksi, mis. user.login)
 * - action prefix -> log_name (namespace modul, mis. user)
 * - description   -> tetap narasi manusia
 * - tenant_id     -> properties->tenant_id
 * - causer_id     -> morph causer (FK dilepas agar audit immutable)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('log_name')->nullable()->after('id')->index();
            $table->string('event')->nullable()->after('description');
            $table->string('causer_type')->nullable()->after('event');
            $table->json('attribute_changes')->nullable()->after('causer_id');
        });

        $this->backfill();

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['causer_id']);
            $table->dropIndex(['tenant_id', 'action']);
            $table->dropIndex(['tenant_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['action', 'tenant_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['causer_type', 'causer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['causer_type', 'causer_id']);
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
            $table->string('action')->nullable()->after('log_name');
        });

        DB::table('audit_logs')->update(['action' => DB::raw('event')]);

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['log_name']);
            $table->dropColumn(['log_name', 'event', 'causer_type', 'attribute_changes']);
            $table->index(['tenant_id', 'action']);
        });
    }

    /**
     * Backfill kolom spatie dari data native lama, dan pindahkan tenant_id
     * ke dalam properties agar tidak hilang saat kolomnya dilepas.
     */
    private function backfill(): void
    {
        DB::table('audit_logs')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $properties = json_decode($row->properties ?? '{}', true) ?: [];

                if ($row->tenant_id !== null && ! array_key_exists('tenant_id', $properties)) {
                    $properties['tenant_id'] = $row->tenant_id;
                }

                DB::table('audit_logs')->where('id', $row->id)->update([
                    'log_name' => str_contains((string) $row->action, '.')
                        ? strtok((string) $row->action, '.')
                        : null,
                    'event' => $row->action,
                    'description' => $row->description ?? $row->action,
                    'causer_type' => $row->causer_id !== null ? 'App\Models\User' : null,
                    'properties' => json_encode($properties),
                ]);
            }
        });
    }
};
