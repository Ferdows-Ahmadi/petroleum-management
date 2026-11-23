<?php

namespace App\Enums;

enum PartnerRole: string
{
    case Customer = 'CUSTOMER';
    case Supplier = 'SUPPLIER';
    case Saraf = 'SARAF';
    case Partner = 'PARTNER';

    public function label(): string
    {
        return __("roles.partner.{$this->value}");
    }
}
