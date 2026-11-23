<?php

namespace App\Enums;

enum MoneyMovementStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';

    public function label(): string
    {
        return __("finance.status.{$this->value}");
    }
}
