<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\CommissionRuleType;
use App\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class CommissionRule extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'therapist_id',
        'type',
        'amount',
        'percent',
        'min_revenue',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    /**
     * Nilai yang bila diubah berarti kebijakannya berganti, bukan sekadar
     * penamaan dirapikan. Perubahan pada salah satunya melahirkan versi baru
     * supaya periode yang sudah lewat tetap dihitung dengan tarif lamanya.
     *
     * @var list<string>
     */
    public const RATE_FIELDS = ['type', 'amount', 'percent', 'min_revenue', 'therapist_id'];

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    /**
     * Berlaku untuk terapis ini? Aturan tanpa terapis berlaku untuk semua.
     */
    public function appliesTo(?int $therapistId): bool
    {
        return $this->therapist_id === null || $this->therapist_id === $therapistId;
    }

    /** Versi yang masih berlaku; yang sudah ditutup tidak ikut terdaftar. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('effective_to');
    }

    /**
     * Versi yang berlaku pada satu saat tertentu. Dipakai perhitungan fee:
     * tiap transaksi dinilai dengan tarif yang berlaku pada tanggalnya,
     * bukan tarif yang kebetulan aktif saat tombol hitung ditekan.
     */
    public function scopeInForceAt(Builder $query, CarbonInterface $moment): Builder
    {
        return $query
            // `effective_from` kosong berarti tarif perdana: berlaku sejak
            // awal. Klinik yang baru menyetel aturannya hari ini tetap bisa
            // menghitung fee bulan lalu.
            ->where(fn (Builder $q) => $q
                ->whereNull('effective_from')
                ->orWhere('effective_from', '<=', $moment))
            ->where(fn (Builder $q) => $q
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>', $moment));
    }

    protected function casts(): array
    {
        return [
            'type' => CommissionRuleType::class,
            'amount' => 'decimal:2',
            'percent' => 'decimal:2',
            'min_revenue' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }
}
