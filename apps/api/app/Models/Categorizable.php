<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris pivot kategori ↔ item.
 *
 * Dimodelkan supaya jumlah pemakaian sebuah kategori bisa dihitung dalam satu
 * kueri, tanpa menjumlahkan tiga relasi terpisah. Tidak bertenant: barisnya
 * hanya penghubung, dan kedua ujungnya sudah bertenant.
 */
class Categorizable extends Model
{
    protected $table = 'categorizables';

    public $timestamps = false;

    protected $fillable = ['category_id', 'categorizable_type', 'categorizable_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
