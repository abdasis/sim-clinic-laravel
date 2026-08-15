<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\WhatsappDriver;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([TenantScope::class])]
class WhatsappSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'driver', 'api_url', 'api_token'];

    protected function casts(): array
    {
        return [
            'driver' => WhatsappDriver::class,
        ];
    }

    /** Token disembunyikan dari response; cukup penanda sudah diisi. */
    protected $hidden = ['api_token'];

    public function isGatewayReady(): bool
    {
        return $this->driver === WhatsappDriver::Gateway
            && filled($this->api_url)
            && filled($this->api_token);
    }
}
