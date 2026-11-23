<?php

namespace App\Enums;

enum CapitalTransactionStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';

    public function label(): string
    {
        return __("capital.status.{$this->value}");
    }
}
