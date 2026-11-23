<?php

namespace App\Services;

use App\Enums\DealStatus;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\MoneyMovementStatus;
use App\Enums\TransactionStatus;
use App\Models\DealDestination;
use App\Models\DealSource;
use App\Models\FinancialTransaction;
use App\Models\MoneyMovement;
use App\Models\PartnerOpeningBalance;
use App\Models\Purchase;
use App\Models\Sale;
use Carbon\CarbonInterface;

class PartnerDirectorySummary
{
    /**
     * @param  list<int>  $partnerIds
     * @return array<int, array{balance: string, last_activity: string}>
     */
    public function forPartnerIds(array $partnerIds): array
    {
        $summaries = [];
        if ($partnerIds === []) {
            return $summaries;
        }

        // Fold stored decimal amounts individually, as the statement does. SQLite SUM uses floating point.
        $record = static function (int $partnerId, CarbonInterface $date, string $amount, int $direction) use (&$summaries): void {
            $balance = $summaries[$partnerId]['balance'] ?? '0.00';
            $summaries[$partnerId] = [
                'balance' => $direction === 1 ? bcadd($balance, $amount, 2) : bcsub($balance, $amount, 2),
                'last_activity' => max($date->toDateString(), $summaries[$partnerId]['last_activity'] ?? ''),
            ];
        };

        $openings = PartnerOpeningBalance::query()
            ->whereIntegerInRaw('business_partner_id', $partnerIds)
            ->select('business_partner_id', 'effective_date', 'amount_usd', 'balance_type');
        foreach ($openings->cursor() as $opening) {
            $record($opening->business_partner_id, $opening->effective_date, $opening->amount_usd, $opening->balance_type->direction());
        }

        // A confirmed trade with no lines still appears on the statement with a zero amount.
        $sales = Sale::query()
            ->leftJoin('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->whereIntegerInRaw('sales.business_partner_id', $partnerIds)
            ->where('sales.status', TransactionStatus::Confirmed)
            ->select('sales.business_partner_id', 'sales.sale_date', 'sale_items.amount')
            ->withCasts(['amount' => 'decimal:2']);
        foreach ($sales->cursor() as $sale) {
            $record($sale->business_partner_id, $sale->sale_date, $sale->amount ?? '0.00', 1);
        }

        $purchases = Purchase::query()
            ->leftJoin('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->whereIntegerInRaw('purchases.business_partner_id', $partnerIds)
            ->where('purchases.status', TransactionStatus::Confirmed)
            ->select('purchases.business_partner_id', 'purchases.purchase_date', 'purchase_items.amount')
            ->withCasts(['amount' => 'decimal:2']);
        foreach ($purchases->cursor() as $purchase) {
            $record($purchase->business_partner_id, $purchase->purchase_date, $purchase->amount ?? '0.00', -1);
        }

        $destinations = DealDestination::query()
            ->join('deals', 'deals.id', '=', 'deal_destinations.deal_id')
            ->whereIntegerInRaw('deal_destinations.customer_id', $partnerIds)
            ->where('deals.status', DealStatus::Completed)
            ->select('deal_destinations.customer_id', 'deals.deal_date', 'deal_destinations.amount')
            ->withCasts(['deal_date' => 'date']);
        foreach ($destinations->cursor() as $destination) {
            $record($destination->customer_id, $destination->deal_date, $destination->amount, 1);
        }

        $sources = DealSource::query()
            ->join('deals', 'deals.id', '=', 'deal_sources.deal_id')
            ->whereIntegerInRaw('deal_sources.supplier_id', $partnerIds)
            ->where('deals.status', DealStatus::Completed)
            ->select('deal_sources.supplier_id', 'deals.deal_date', 'deal_sources.amount')
            ->withCasts(['deal_date' => 'date']);
        foreach ($sources->cursor() as $source) {
            $record($source->supplier_id, $source->deal_date, $source->amount ?? '0.00', -1);
        }

        $payments = FinancialTransaction::query()
            ->whereIntegerInRaw('business_partner_id', $partnerIds)
            ->where('status', FinancialTransactionStatus::Confirmed)
            ->whereIn('transaction_type', [FinancialTransactionType::PaymentReceived, FinancialTransactionType::PaymentPaid])
            ->select('business_partner_id', 'transaction_date', 'amount_usd', 'transaction_type');
        foreach ($payments->cursor() as $payment) {
            $record($payment->business_partner_id, $payment->transaction_date, $payment->amount_usd, $payment->transaction_type->partnerDirection());
        }

        $movements = MoneyMovement::query()
            ->whereIntegerInRaw('counterparty_id', $partnerIds)
            ->where('status', MoneyMovementStatus::Confirmed)
            ->select('counterparty_id', 'movement_date', 'amount_usd', 'movement_type');
        foreach ($movements->cursor() as $movement) {
            $direction = $movement->movement_type->counterpartyDirection();
            if ($direction !== 0) {
                $record($movement->counterparty_id, $movement->movement_date, $movement->amount_usd, $direction);
            }
        }

        return $summaries;
    }
}
