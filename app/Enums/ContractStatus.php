<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return __("contracts.status.{$this->value}");
    }
}
