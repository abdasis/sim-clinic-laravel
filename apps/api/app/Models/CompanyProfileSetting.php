<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setelan tunggal company profile per tenant: identitas situs, kanal chat,
 * tautan sosial/marketplace, dan saklar publikasi landing.
 */
#[ScopedBy([TenantScope::class])]
class CompanyProfileSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'logo_path',
        'site_name',
        'address',
        'maps_url',
        'tagline',
        'operating_hours',
        'receipt_note',
        'copyright_text',
        'chat_channels',
        'social_links',
        'marketplace_links',
        'default_locale',
        'is_published',
    ];

    /**
     * Klinik buka pada saat itu?
     *
     * Perbandingannya sebagai string "HH:mm" — sah secara leksikograf karena
     * jamnya selalu bernol di depan, dan menghindari membangun objek waktu
     * hanya untuk membandingkan dua angka.
     *
     * Kunci harinya memakai format('l'), bukan translatedFormat: yang kedua
     * ikut APP_LOCALE dan akan menghasilkan "senin" alih-alih "monday",
     * sehingga tidak pernah cocok dengan kunci yang disimpan frontend.
     *
     * ponytail: jam operasional yang belum diatur berarti selalu buka,
     * mengikuti pola "kosong = semua boleh" yang sudah dipakai di
     * ChatbotSetting::allowsBooking. Kalau nanti seluruh klinik wajib
     * mengisinya, ganti dengan jam bawaan dan tolak yang di luar itu.
     */
    public function isOpenAt(CarbonInterface $at): bool
    {
        $hours = $this->operating_hours;

        if (blank($hours)) {
            return true;
        }

        $day = $hours[strtolower($at->format('l'))] ?? null;

        if (! is_array($day) || ! ($day['is_open'] ?? false)) {
            return false;
        }

        $open = $day['open'] ?? null;
        $close = $day['close'] ?? null;

        if (! is_string($open) || ! is_string($close)) {
            return false;
        }

        $moment = $at->format('H:i');

        return $moment >= $open && $moment <= $close;
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'site_name' => 'array',
            'operating_hours' => 'array',
            'chat_channels' => 'array',
            'social_links' => 'array',
            'marketplace_links' => 'array',
        ];
    }
}
