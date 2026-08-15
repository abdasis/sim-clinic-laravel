<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\PaymentStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([TenantScope::class])]
class Transaction extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'booking_id',
        'cashier_id',
        'therapist_id',
        'invoice_number',
        'subtotal',
        'paid_amount',
        'payment_status',
        'issued_at',
        'print_count',
        'last_printed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'issued_at' => 'datetime',
            'last_printed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Nomor invoice per tenant per hari: INV-YYYYMMDD-XXXX.
     *
     * Wajib dipanggil di dalam DB transaction — baris hari ini dikunci agar
     * dua kasir yang menekan simpan bersamaan tidak mendapat nomor sama.
     *
     * ponytail: urutan 4 digit cukup sampai 9999 transaksi per tenant per
     * hari; pakai tabel sequence bila terlampaui. Penguncian baru menahan
     * sejak transaksi pertama hari itu ada — untuk dua kasir yang menekan
     * simpan pada detik yang sama di hari kosong, unique (tenant_id,
     * invoice_number) tetap pertahanan terakhir.
     */
    public static function generateInvoiceNumber(): string
    {
        $tenantId = app()->bound('tenant') && app('tenant') !== null ? app('tenant')->id : null;

        // PostgreSQL menolak FOR UPDATE bersama fungsi agregat, jadi baris
        // hari ini diambil dulu (terkunci) lalu dihitung di PHP.
        $countToday = static::withoutGlobalScope(TenantScope::class)
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->lockForUpdate()
            ->pluck('id')
            ->count();

        $sequence = str_pad((string) ($countToday + 1), 4, '0', STR_PAD_LEFT);

        return 'INV-'.now()->format('Ymd').'-'.$sequence;
    }

    /**
     * Sisa yang masih harus dibayar; tidak pernah negatif walau lebih bayar.
     */
    public function outstandingAmount(): float
    {
        return max(0, (float) $this->subtotal - (float) $this->paid_amount);
    }
}
