<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Bagian pengaturan WhatsApp yang memang milik tiap klinik: nama sesi WAHA.
 * Alamat server dan API key-nya central, di WahaSetting.
 */
#[ScopedBy([TenantScope::class])]
class WhatsappSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'session'];

    public function isConfigured(): bool
    {
        return filled($this->session);
    }
}
