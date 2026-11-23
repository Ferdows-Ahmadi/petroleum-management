<?php

namespace App\Services;

use App\Enums\DealStatus;
use App\Enums\MoneyMovementStatus;
use App\Enums\MoneyMovementType;
use App\Models\Deal;

class DealMoneySummaryService
{
    /** @return array<string, mixed> */
    public function forDeal(Deal $deal): array
    {
        $deal->loadMissing(['sources', 'destinations', 'moneyMovements.counterparty', 'moneyMovements.saraf', 'moneyMovements.account']);

        return $this->totals($deal) + [
            'movements' => $deal->moneyMovements->sortByDesc(fn ($movement) => $movement->movement_date->toDateString().'-'.str_pad((string) $movement->id, 10, '0', STR_PAD_LEFT))->values(),
        ];
    }

    /** @return array<string, string> */
    public function totals(Deal $deal): array
    {
        $confirmed = $deal->moneyMovements->where('status', MoneyMovementStatus::Confirmed);
        $received = $confirmed->where('movement_type', MoneyMovementType::CustomerReceipt)
            ->reduce(fn (string $sum, $movement): string => bcadd($sum, $movement->amount_usd, 2), '0.00');
        $paid = $confirmed->where('movement_type', MoneyMovementType::SupplierPayment)
            ->reduce(fn (string $sum, $movement): string => bcadd($sum, $movement->amount_usd, 2), '0.00');
        $receivable = $deal->total_destination_amount;
        $payable = $deal->total_source_amount;
        $remainingReceivable = bcsub($receivable, $received, 2);
        $remainingPayable = bcsub($payable, $paid, 2);
        $hasAny = bccomp($received, '0', 2) > 0 || bccomp($paid, '0', 2) > 0;
        $settled = bccomp($remainingReceivable, '0', 2) <= 0 && bccomp($remainingPayable, '0', 2) <= 0;

        return [
            'purchase_obligation' => $payable,
            'sale_receivable' => $receivable,
            'paid_to_supplier' => $paid,
            'received_from_customer' => $received,
            'remaining_payable' => $remainingPayable,
            'remaining_receivable' => $remainingReceivable,
            'settlement_state' => $deal->status !== DealStatus::Completed ? 'PENDING' : ($settled ? 'SETTLED' : ($hasAny ? 'PARTIAL' : 'UNPAID')),
        ];
    }
}
