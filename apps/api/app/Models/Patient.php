<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[ScopedBy([TenantScope::class])]
class Patient extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'birth_date',
        'gender',
        'whatsapp',
        'address',
        'notes',
        'referred_by',
        'membership_tier_id',
        'member_since',
        'member_until',
        'whatsapp_opt_in',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_in' => 'boolean',
            'birth_date' => 'date',
            'member_since' => 'date',
            'member_until' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function membershipTier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class);
    }

    /**
     * Tingkat yang benar-benar berlaku hari ini, atau null.
     *
     * Tiga hal harus benar sekaligus: tingkatnya ada, tingkatnya masih aktif,
     * dan masa berlakunya belum lewat. Masa berlaku yang kosong berarti tidak
     * pernah kedaluwarsa — sebagian klinik menjual keanggotaan seumur
     * pemakaian, bukan berlangganan.
     */
    public function activeMembership(?Carbon $on = null): ?MembershipTier
    {
        // `copy()` di sini bukan kosmetik: `startOfDay()` Carbon mengubah
        // objeknya sendiri, bukan mengembalikan salinan. Tanpa `copy()`,
        // `$on` yang dikirim pemanggil — sering `$issuedAt` yang juga dipakai
        // menyimpan waktu transaksi — ikut terpangkas ke tengah malam begitu
        // fungsi ini selesai, dan nota yang seharusnya mencatat jam sungguhan
        // diam-diam tersimpan sebagai 00:00:00.
        $on = ($on ?? Carbon::today())->copy()->startOfDay();
        $tier = $this->membershipTier;

        if ($tier === null || ! $tier->isActive()) {
            return null;
        }

        if ($this->member_since !== null && $this->member_since->copy()->startOfDay()->greaterThan($on)) {
            return null;
        }

        if ($this->member_until !== null && $this->member_until->copy()->startOfDay()->lessThan($on)) {
            return null;
        }

        return $tier;
    }

    /** Staf yang membawa pasien ini — dasar bonus pasien baru. */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
