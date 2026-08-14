<?php

namespace App\Models;

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
}
