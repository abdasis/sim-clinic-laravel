<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Tarif poin loyalitas satu klinik.
 *
 * Seberapa murah hati program poin adalah urusan margin tiap klinik, jadi
 * angkanya data — bukan konstanta yang cuma bisa berubah lewat rilis.
 *
 * Klinik yang belum pernah menyetelnya memakai bawaan di bawah, dan barisnya
 * memang belum ada sampai ada yang menyimpan: baris kosong yang dibuat
 * otomatis membuat "belum pernah disetel" tidak bisa dibedakan dari "disetel
 * persis seperti bawaan".
 */
#[ScopedBy([TenantScope::class])]
class LoyaltySetting extends Model
{
    use BelongsToTenant;

    /** Rupiah belanja untuk mendapat satu poin. */
    public const DEFAULT_EARN_RATE = 10_000.0;

    /** Rupiah potongan dari satu poin yang ditukar. */
    public const DEFAULT_REDEEM_RATE = 1_000.0;

    /** Tukar paling sedikit segini. */
    public const DEFAULT_MIN_REDEEM = 10;

    protected $fillable = ['tenant_id', 'earn_rate', 'redeem_rate', 'min_redeem'];

    protected function casts(): array
    {
        return [
            'earn_rate' => 'decimal:2',
            'redeem_rate' => 'decimal:2',
            'min_redeem' => 'integer',
        ];
    }

    /**
     * Tarif yang berlaku untuk klinik yang sedang aktif.
     *
     * Selalu menjawab, juga saat barisnya belum ada — pemanggilnya menghitung
     * uang dan poin, dan tidak punya jawaban yang masuk akal untuk null.
     *
     * @return array{earn_rate: float, redeem_rate: float, min_redeem: int}
     */
    public static function current(): array
    {
        $setting = static::query()->first();

        return [
            'earn_rate' => self::positive(
                $setting?->earn_rate,
                self::DEFAULT_EARN_RATE,
            ),
            'redeem_rate' => self::positive(
                $setting?->redeem_rate,
                self::DEFAULT_REDEEM_RATE,
            ),
            'min_redeem' => max(1, (int) ($setting?->min_redeem ?? self::DEFAULT_MIN_REDEEM)),
        ];
    }

    /**
     * Tarif nol membuat pembagian poin meledak dan penukaran jadi gratis tak
     * terbatas. Divalidasi di FormRequest, dijaga sekali lagi di sini karena
     * baris lama dari sebelum aturan itu ada tetap harus aman dibaca.
     */
    private static function positive(mixed $value, float $fallback): float
    {
        $rate = (float) ($value ?? 0);

        return $rate > 0 ? $rate : $fallback;
    }
}
