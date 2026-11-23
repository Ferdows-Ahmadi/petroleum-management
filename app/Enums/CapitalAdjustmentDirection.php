<?php

namespace App\Enums;

enum CapitalAdjustmentDirection: string
{
    case Increase = 'INCREASE';
    case Decrease = 'DECREASE';

    public function direction(): int
    {
        return $this === self::Increase ? 1 : -1;
    }

    public function label(): string
    {
        return __("capital.adjustment_directions.{$this->value}");
    }
}
