<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return __("trades.status.{$this->value}");
    }
}
