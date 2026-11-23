<?php

namespace App\Enums;

enum DealDestinationType: string
{
    case Customer = 'CUSTOMER';
    case Storage = 'STORAGE';

    public function label(): string
    {
        return __("settlements.destination_types.{$this->value}");
    }
}
