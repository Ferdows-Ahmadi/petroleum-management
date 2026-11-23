<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case PurchaseIn = 'PURCHASE_IN';
    case SaleOut = 'SALE_OUT';
    case DealStorageIn = 'DEAL_STORAGE_IN';
    case DealStorageOut = 'DEAL_STORAGE_OUT';

    public function direction(): int
    {
        return match ($this) {
            self::PurchaseIn, self::DealStorageIn => 1,
            self::SaleOut, self::DealStorageOut => -1,
        };
    }

    public function label(): string
    {
        return __("inventory.movement_types.{$this->value}");
    }
}
