<?php

namespace App\Services;

use App\Enums\MoneyMovementStatus;
use App\Models\BusinessPartner;
use Illuminate\Support\Collection;

class SarafLedgerService
{
    /** @return Collection<int, array<string, mixed>> */
    public function statementFor(BusinessPartner $saraf, ?string $throughDate = null): Collection
    {
        $running = '0.00';

        return $saraf->sarafMoneyMovements()
            ->where('status', MoneyMovementStatus::Confirmed->value)
            ->when($throughDate, fn ($query) => $query->whereDate('movement_date', '<=', $throughDate))
            ->with(['counterparty', 'deal', 'account'])
            ->orderBy('movement_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function ($movement) use (&$running): array {
                $direction = $movement->movement_type->sarafDirection();
                $effect = $direction === 1 ? $movement->amount_usd : bcmul($movement->amount_usd, '-1', 2);
                $running = bcadd($running, $effect, 2);

                return [
                    'date' => $movement->movement_date->toDateString(),
                    'sort_at' => $movement->created_at,
                    'id' => $movement->id,
                    'type' => $movement->movement_type->value,
                    'reference' => $movement->reference_code,
                    'source' => $movement,
                    'related_party' => $movement->counterparty?->name,
                    'deal' => $movement->deal?->reference_code,
                    'incoming' => $direction === 1 ? $movement->amount_usd : null,
                    'outgoing' => $direction === -1 ? $movement->amount_usd : null,
                    'effect' => $effect,
                    'running_balance' => $running,
                ];
            });
    }

    public function balanceFor(BusinessPartner $saraf, ?string $throughDate = null): string
    {
        return $this->statementFor($saraf, $throughDate)->last()['running_balance'] ?? '0.00';
    }

    public function balanceBefore(BusinessPartner $saraf, string $date): string
    {
        $last = $saraf->sarafMoneyMovements()
            ->where('status', MoneyMovementStatus::Confirmed->value)
            ->whereDate('movement_date', '<', $date)
            ->with(['counterparty', 'deal', 'account'])
            ->orderBy('movement_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $balance = '0.00';
        foreach ($last as $movement) {
            $balance = $movement->movement_type->sarafDirection() === 1
                ? bcadd($balance, $movement->amount_usd, 2)
                : bcsub($balance, $movement->amount_usd, 2);
        }

        return $balance;
    }
}
