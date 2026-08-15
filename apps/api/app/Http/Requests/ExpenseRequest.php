<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

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
            'category' => ['required', new Enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'spent_at' => __('expense.spent_at'),
            'category' => __('expense.category_label'),
            'description' => __('expense.description'),
            'amount' => __('expense.amount'),
            'note' => __('expense.note'),
        ];
    }
}
