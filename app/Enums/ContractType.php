<?php

namespace App\Enums;

enum ContractType: string
{
    case Purchase = 'PURCHASE';
    case Sale = 'SALE';
    case General = 'GENERAL';

    public function label(): string
    {
        return __("contracts.types.{$this->value}");
    }
}
