<?php

namespace App\Enums;

enum CommercialRecordStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';

    public function label(): string
    {
        return __("settlements.record_status.{$this->value}");
    }
}
