<?php

namespace App\Services;

use App\Actions\Expense\CreateExpenseAction;
use App\Actions\Expense\DeleteExpenseAction;
use App\Actions\Expense\UpdateExpenseAction;
use App\Models\Expense;

/**
 * Orkestrasi pengeluaran klinik. Sengaja tipis: tiap operasi menyentuh satu
 * baris, jadi tidak ada batas transaksi yang perlu dipegang di sini.
 */
class ExpenseService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Expense
    {
        return app(CreateExpenseAction::class)->handle($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        return app(UpdateExpenseAction::class)->handle($expense, $data);
    }

    public function delete(Expense $expense): void
    {
        app(DeleteExpenseAction::class)->handle($expense);
    }
}
