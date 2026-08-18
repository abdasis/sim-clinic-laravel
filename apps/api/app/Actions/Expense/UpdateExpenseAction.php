<?php

namespace App\Actions\Expense;

use App\Actions\LogAuditAction;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;

class UpdateExpenseAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Expense $expense, array $data): Expense
    {
        $old = $expense->only(array_keys($data));

        $expense->update($data);

        // `category_id` bukan kolom di tabel ini melainkan baris pivot, jadi
        // tidak ikut terbawa mass assignment dan dipasang terpisah.
        if (array_key_exists('category_id', $data)) {
            $expense->syncCategory($data['category_id'] !== null ? (int) $data['category_id'] : null);
        }

        app(LogAuditAction::class)->handle(
            'expense.updated',
            $expense,
            Auth::user(),
            ['old' => $old, 'new' => $expense->only(array_keys($data))],
            'Memperbarui pengeluaran '.$expense->description.'.',
        );

        return $expense;
    }
}
