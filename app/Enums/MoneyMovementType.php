<?php

namespace App\Enums;

enum MoneyMovementType: string
{
    case CustomerReceipt = 'CUSTOMER_RECEIPT';
    case SupplierPayment = 'SUPPLIER_PAYMENT';
    case OtherReceipt = 'OTHER_RECEIPT';
    case OtherPayment = 'OTHER_PAYMENT';

    public function label(): string
    {
        return __("finance.money_types.{$this->value}");
    }

    public function accountDirection(): int
    {
        return match ($this) {
            self::CustomerReceipt, self::OtherReceipt => 1,
            self::SupplierPayment, self::OtherPayment => -1,
        };
    }

    public function counterpartyDirection(): int
    {
        return match ($this) {
            self::CustomerReceipt => -1,
            self::SupplierPayment => 1,
            self::OtherReceipt, self::OtherPayment => 0,
        };
    }

    public function sarafDirection(): int
    {
        return $this->accountDirection();
    }

    public function isCommercial(): bool
    {
        return in_array($this, [self::CustomerReceipt, self::SupplierPayment], true);
    }
}
