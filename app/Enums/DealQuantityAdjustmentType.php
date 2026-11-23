<?php

namespace App\Enums;

enum DealQuantityAdjustmentType: string
{
    case Shortage = 'SHORTAGE';
    case Excess = 'EXCESS';

    public function label(): string
    {
        return __("settlements.adjustment_types.{$this->value}");
    }
}
