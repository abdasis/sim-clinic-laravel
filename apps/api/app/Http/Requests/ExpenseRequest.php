<?php

namespace App\Http\Requests;

use App\Enums\CategorizableType;
use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'spent_at' => ['required', 'date'],
            // Wajib: laporan pengeluaran dikelompokkan per kategori, dan
            // baris tanpa kategori hilang dari rekapnya.
            'category_id' => [
                'required',
                TenantRule::exists('categories')->where('type', CategorizableType::Expense->value),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'spent_at' => __('expense.spent_at'),
            'category_id' => __('expense.category_label'),
            'description' => __('expense.description'),
            'amount' => __('expense.amount'),
            'note' => __('expense.note'),
        ];
    }
}
