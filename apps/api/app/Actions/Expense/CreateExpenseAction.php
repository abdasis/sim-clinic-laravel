<?php

namespace App\Actions\Expense;

use App\Actions\LogAuditAction;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;

/**
 * Catat satu pengeluaran klinik.
 */
class CreateExpenseAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Expense
    {
        $expense = Expense::create([...$data, 'recorded_by' => Auth::id()]);

        $expense->refresh();

        app(LogAuditAction::class)->handle(
            'expense.created',
            $expense,
            Auth::user(),
            ['attributes' => $expense->getAttributes()],
            'Mencatat pengeluaran '.$expense->description.' sebesar Rp'
                .number_format((float) $expense->amount, 0, ',', '.').' pada '
                .$expense->spent_at?->format('d/m/Y').'.',
        );

        return $expense;
    }
}
