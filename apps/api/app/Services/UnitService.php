<?php

namespace App\Services;

use App\Actions\Unit\ArchiveUnitAction;
use App\Actions\Unit\CreateUnitAction;
use App\Actions\Unit\DeleteUnitAction;
use App\Actions\Unit\UpdateUnitAction;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Use case satuan produk. Setiap sentuhan basis data lewat Action; di sini
 * hanya batas transaksinya.
 */
class UnitService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Unit
    {
        return DB::transaction(fn () => app(CreateUnitAction::class)->handle($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Unit $unit, array $data): Unit
    {
        return DB::transaction(fn () => app(UpdateUnitAction::class)->handle($unit, $data));
    }

    public function archive(Unit $unit): Unit
    {
        return DB::transaction(fn () => app(ArchiveUnitAction::class)->handle($unit));
    }

    /** Hapus permanen; ditolak bila masih dipakai produk. */
    public function delete(Unit $unit): void
    {
        DB::transaction(fn () => app(DeleteUnitAction::class)->handle($unit));
    }
}
