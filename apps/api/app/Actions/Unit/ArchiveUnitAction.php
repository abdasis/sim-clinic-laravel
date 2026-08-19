<?php

namespace App\Actions\Unit;

use App\Actions\LogAuditAction;
use App\Enums\ServiceStatus;
use App\Models\Unit;

/**
 * Arsipkan satuan: hilang dari pilihan formulir produk, tapi produk yang
 * sudah memakainya tetap menampilkan satuannya.
 */
class ArchiveUnitAction
{
    public function handle(Unit $unit): Unit
    {
        $old = $unit->status;

        $unit->forceFill(['status' => ServiceStatus::Archived])->save();

        app(LogAuditAction::class)->handle(
            'unit.archived',
            $unit,
            auth()->user(),
            ['old' => ['status' => $old], 'new' => ['status' => $unit->status]],
            'Mengarsipkan satuan produk '.$unit->name.'.',
        );

        return $unit;
    }
}
