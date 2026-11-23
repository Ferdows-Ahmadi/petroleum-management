<?php

namespace App\Enums;

enum WeighingMode: string
{
    case DirectNet = 'DIRECT_NET';
    case TwoStage = 'TWO_STAGE';

    public function label(): string
    {
        return __('weighings.mode.'.$this->value);
    }
}
