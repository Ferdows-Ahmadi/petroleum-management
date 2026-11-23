<?php

namespace App\Enums;

enum WeighingStatus: string
{
    case Draft = 'DRAFT';
    case Finalized = 'FINALIZED';

    public function label(): string
    {
        return __("weighings.status.{$this->value}");
    }
}
