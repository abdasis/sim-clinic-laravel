<?php

namespace App\Actions\Expense;

use App\Actions\LogAuditAction;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;

/**
 * Hapus pengeluaran secara lunak — laporan bulan berjalan boleh berubah,
 * tapi jejak angkanya tetap bisa ditelusuri saat diaudit.
 */
class DeleteExpenseAction
{
    public function handle(Expense $expense): void
    {
        $snapshot = $expense->getAttributes();
        $description = $expense->description;

        $expense->delete();

        app(LogAuditAction::class)->handle(
            'expense.deleted',
            $expense,
            Auth::user(),
            ['attributes' => $snapshot],
            'Menghapus pengeluaran '.$description.'.',
        );
    }
}
