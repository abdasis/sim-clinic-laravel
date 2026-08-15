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
