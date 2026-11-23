<?php

namespace App\Enums;

enum PartnerOpeningBalanceType: string
{
    case Receivable = 'RECEIVABLE';
    case Payable = 'PAYABLE';

    public function label(): string
    {
        return __("finance.opening_types.{$this->value}");
    }

    public function direction(): int
    {
        return $this === self::Receivable ? 1 : -1;
    }
}
