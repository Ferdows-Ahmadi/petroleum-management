<?php

namespace App\Enums;

enum FinancialTransactionType: string
{
    case PaymentReceived = 'PAYMENT_RECEIVED';
    case PaymentPaid = 'PAYMENT_PAID';
    case Expense = 'EXPENSE';

    public function label(): string
    {
        return __("finance.transaction_types.{$this->value}");
    }

    public function accountDirection(): int
    {
        return $this === self::PaymentReceived ? 1 : -1;
    }

    public function partnerDirection(): int
    {
        return match ($this) {
            self::PaymentReceived => -1,
            self::PaymentPaid => 1,
            self::Expense => 0,
        };
    }
}
