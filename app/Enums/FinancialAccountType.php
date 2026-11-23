<?php

namespace App\Enums;

enum FinancialAccountType: string
{
    case Cash = 'CASH';
    case Exchange = 'EXCHANGE';
    case Bank = 'BANK';
    case Other = 'OTHER';

    public function label(): string
    {
        return __("finance.account_types.{$this->value}");
    }
}
