<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ExpenseCategory;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([TenantScope::class])]
class Expense extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'spent_at',
        'category',
        'description',
        'amount',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'spent_at' => 'date',
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Batasi ke rentang tanggal; batas awal dan akhir ikut terhitung.
     */
    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('spent_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('spent_at', '<=', $to));
    }
}
