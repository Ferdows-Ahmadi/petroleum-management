<?php

namespace App\Enums;

enum DealAllocationMethod: string
{
    case Automatic = 'AUTOMATIC';
    case Manual = 'MANUAL';

    public function label(): string
    {
        return __("settlements.allocation_methods.{$this->value}");
    }
}
