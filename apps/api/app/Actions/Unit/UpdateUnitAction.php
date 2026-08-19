<?php

namespace App\Actions\Unit;

use App\Actions\LogAuditAction;
use App\Models\Unit;

class UpdateUnitAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Unit $unit, array $data): Unit
    {
        $old = $unit->only(array_keys($data));

        $unit->update($data);

        app(LogAuditAction::class)->handle(
            'unit.updated',
            $unit,
            auth()->user(),
            ['old' => $old, 'new' => $unit->only(array_keys($data))],
            'Memperbarui satuan produk '.$unit->name.'.',
        );

        return $unit;
    }
}
