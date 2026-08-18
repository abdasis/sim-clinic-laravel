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

        // `category_id` bukan kolom di tabel ini melainkan baris pivot, jadi
        // tidak ikut terbawa mass assignment dan dipasang terpisah.
        if (array_key_exists('category_id', $data)) {
            $expense->syncCategory($data['category_id'] !== null ? (int) $data['category_id'] : null);
        }

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
