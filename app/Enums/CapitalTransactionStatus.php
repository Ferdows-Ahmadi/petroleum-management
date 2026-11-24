<?php

namespace App\Enums;

enum CapitalTransactionStatus: string
{
    case Draft = 'DRAFT';
    case PendingApproval = 'PENDING_APPROVAL';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';

    public function awaitsConfirmation(): bool
    {
        return $this === self::Draft || $this === self::PendingApproval;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Confirmed => 'badge-green',
            self::Cancelled => 'badge-gray',
            default => 'badge-amber',
        };
    }

    public function label(): string
    {
        return __("capital.status.{$this->value}");
    }
}
