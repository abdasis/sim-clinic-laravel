<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setelan chatbot WhatsApp milik satu klinik.
 */
#[ScopedBy([TenantScope::class])]
class ChatbotSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'is_active',
        'agent_name',
        'agent_avatar_path',
        'bookable_service_ids',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'bookable_service_ids' => 'array',
        ];
    }

    /**
     * Boleh-tidaknya satu layanan dibooking lewat chat.
     *
     * Daftar kosong berarti belum dibatasi — seluruh layanan boleh. Kalau
     * daftar kosong diperlakukan sebagai "tidak ada yang boleh", klinik yang
     * baru menyalakan chatbot akan mendapati booking selalu ditolak tanpa
     * pernah tahu sebabnya.
     */
    public function allowsBooking(int $serviceId): bool
    {
        return blank($this->bookable_service_ids)
            || in_array($serviceId, array_map('intval', $this->bookable_service_ids), true);
    }
}
