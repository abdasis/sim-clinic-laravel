<?php

namespace App\Actions\Unit;

use App\Actions\LogAuditAction;
use App\Models\Unit;

/**
 * Hapus permanen. Ditolak selama masih ada produk yang memakainya: foreign
 * key-nya RESTRICT, dan menghapusnya diam-diam berarti meninggalkan produk
 * tanpa satuan yang bisa dibaca.
 */
class DeleteUnitAction
{
    public function handle(Unit $unit): void
    {
        $used = $unit->usageCount();

        abort_if($used > 0, 422, __('unit.in_use', ['count' => $used]));

        $attributes = $unit->getAttributes();
        $name = $unit->name;

        $unit->delete();

        app(LogAuditAction::class)->handle(
            'unit.deleted',
            null,
            auth()->user(),
            ['old' => $attributes],
            'Menghapus permanen satuan produk '.$name.'.',
        );
    }
}
