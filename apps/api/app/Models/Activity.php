<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Audit log (spec 001, FR-028) di atas spatie/laravel-activitylog.
 * Tabel tetap `audit_logs`; tenant disimpan di properties->tenant_id.
 *
 * ponytail: index JSON path properties->tenant_id ditambah saat query
 * per tenant terbukti lambat (lihat docs/erd/audit_logs.md).
 */
class Activity extends BaseActivity
{
    protected $table = 'audit_logs';

    /**
     * Log tidak memakai TenantScope: tenant-nya tersimpan di properties,
     * bukan kolom, dan sebagian baris memang milik platform (tenant null).
     * Penyaringnya dipasang eksplisit di sini supaya satu-satunya cara
     * membaca log klinik lain adalah dengan sengaja melewatinya.
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('properties->tenant_id', $tenantId);
    }

    /**
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $inner) => $inner->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $inner) => $inner->whereDate('created_at', '<=', $to));
    }

    /**
     * Nilai lama dan baru yang dicatat Action saat mengubah data.
     * LogAuditAction menyimpannya sebagai properties.old / properties.new;
     * baris lama dari spatie memakai properties.attributes untuk sisi baru.
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public function diff(): array
    {
        $properties = $this->properties ?? collect();

        return [
            'old' => (array) $properties->get('old', []),
            'new' => (array) $properties->get('new', $properties->get('attributes', [])),
        ];
    }

    /**
     * Nilai yang bukan bagian dari diff — konteks tambahan seperti jumlah
     * baris impor atau alasan pembatalan.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        $properties = ($this->properties ?? collect())->all();

        unset($properties['old'], $properties['new'], $properties['attributes'], $properties['tenant_id']);

        return $properties;
    }
}
