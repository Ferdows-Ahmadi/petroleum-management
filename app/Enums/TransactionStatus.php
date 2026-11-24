<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'DRAFT';
    case AwaitingWeighing = 'AWAITING_WEIGHING';
    case PendingApproval = 'PENDING_APPROVAL';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';

    public function allowsWeighing(): bool
    {
        return $this === self::AwaitingWeighing || $this === self::Draft;
    }

    public function allowsApproval(): bool
    {
        return $this === self::PendingApproval || $this === self::Draft;
    }

    /** @return list<self> */
    public static function purchaseStatuses(): array
    {
        return [self::Draft, self::PendingApproval, self::Confirmed, self::Cancelled];
    }

    public function label(): string
    {
        return __("trades.status.{$this->value}");
    }
}
