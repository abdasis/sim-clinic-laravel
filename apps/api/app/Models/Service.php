<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ServiceStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([TenantScope::class])]
class Service extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'description', 'price', 'status'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => ServiceStatus::class,
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function treatmentRecords(): HasMany
    {
        return $this->hasMany(TreatmentRecord::class);
    }
}
