<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\IsCompanyContent;
use App\Enums\CompanyTreatmentBadge;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etalase treatment di landing. Boleh menaut ke master `Service`, boleh juga
 * berdiri sendiri untuk tindakan yang belum masuk katalog klinik.
 */
#[ScopedBy([TenantScope::class])]
class CompanyTreatment extends Model
{
    use BelongsToTenant, HasFactory, IsCompanyContent;

    protected $fillable = [
        'tenant_id',
        'service_id',
        'slug',
        'name',
        'excerpt',
        'description',
        'image_path',
        'badge',
        'price_label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'badge' => CompanyTreatmentBadge::class,
            'name' => 'array',
            'excerpt' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
