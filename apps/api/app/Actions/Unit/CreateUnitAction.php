<?php

namespace App\Actions\Unit;

use App\Actions\LogAuditAction;
use App\Models\Unit;

class CreateUnitAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Unit
    {
        $unit = Unit::create($data);

        // Status berasal dari default kolom, jadi instance hasil create
        // belum memuatnya sampai di-refresh — tanpa ini responsnya, dan
        // catatan auditnya, mengirim status kosong.
        $unit->refresh();

        app(LogAuditAction::class)->handle(
            'unit.created',
            $unit,
            auth()->user(),
            ['attributes' => $unit->getAttributes()],
            'Menambahkan satuan produk '.$unit->name.'.',
        );

        return $unit;
    }
}
