<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Operational = 'operational';
    case Salary = 'salary';
    case Incentive = 'incentive';
    case Purchase = 'purchase';
    case Rent = 'rent';
    case Utility = 'utility';
    case Marketing = 'marketing';
    case Other = 'other';

    public function label(): string
    {
        return __('expense.category.'.$this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
