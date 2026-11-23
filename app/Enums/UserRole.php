<?php

namespace App\Enums;

enum UserRole: string
{
    case GeneralManager = 'GENERAL_MANAGER';
    case SystemAdmin = 'SYSTEM_ADMIN';
    case Accountant = 'ACCOUNTANT';
    case PurchaseSalesOfficer = 'PURCHASE_SALES_OFFICER';
    case WarehouseOfficer = 'WAREHOUSE_OFFICER';
    case TransportOfficer = 'TRANSPORT_OFFICER';

    public function label(): string
    {
        return __("roles.user.{$this->value}");
    }
}
