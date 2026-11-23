<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Transport = 'TRANSPORT';
    case Weighing = 'WEIGHING';
    case Loading = 'LOADING';
    case Salary = 'SALARY';
    case Repairs = 'REPAIRS';
    case Office = 'OFFICE';
    case Other = 'OTHER';

    public function label(): string
    {
        return __("finance.expense_categories.{$this->value}");
    }
}
