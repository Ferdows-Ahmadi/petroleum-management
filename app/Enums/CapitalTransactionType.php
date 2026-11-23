<?php

namespace App\Enums;

enum CapitalTransactionType: string
{
    case InitialCapital = 'INITIAL_CAPITAL';
    case Contribution = 'CONTRIBUTION';
    case Withdrawal = 'WITHDRAWAL';
    case ProfitAllocation = 'PROFIT_ALLOCATION';
    case LossAllocation = 'LOSS_ALLOCATION';
    case Adjustment = 'ADJUSTMENT';

    public function capitalDirection(?CapitalAdjustmentDirection $adjustment = null): int
    {
        return match ($this) {
            self::InitialCapital, self::Contribution, self::ProfitAllocation => 1,
            self::Withdrawal, self::LossAllocation => -1,
            self::Adjustment => $adjustment?->direction() ?? 0,
        };
    }

    public function isCashMovement(): bool
    {
        return in_array($this, [self::InitialCapital, self::Contribution, self::Withdrawal], true);
    }

    public function accountDirection(): int
    {
        return $this === self::Withdrawal ? -1 : 1;
    }

    public function label(): string
    {
        return __("capital.types.{$this->value}");
    }
}
