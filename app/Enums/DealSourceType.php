<?php

namespace App\Enums;

enum DealSourceType: string
{
    case Supplier = 'SUPPLIER';
    case Storage = 'STORAGE';

    public function label(): string
    {
        return __("settlements.source_types.{$this->value}");
    }
}
