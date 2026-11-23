<?php

namespace App\Enums;

enum DealExpenseCategory: string
{
    case Freight = 'FREIGHT';
    case Driver = 'DRIVER';
    case Road = 'ROAD';
    case Weighbridge = 'WEIGHBRIDGE';
    case Handling = 'HANDLING';
    case Other = 'OTHER';

    public function label(): string
    {
        return __("settlements.expense_categories.{$this->value}");
    }
}
