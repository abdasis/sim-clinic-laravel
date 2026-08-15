<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'phone', 'status'];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Setelan profil perusahaan bersifat singleton per tenant; dipakai antara
     * lain untuk mengisi kop nota.
     */
    public function companyProfile(): HasOne
    {
        return $this->hasOne(CompanyProfileSetting::class);
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }
}
