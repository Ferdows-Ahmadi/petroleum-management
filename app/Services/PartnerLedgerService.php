<?php

namespace App\Services;

use App\Enums\DealStatus;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\MoneyMovementStatus;
use App\Enums\PartnerOpeningBalanceType;
use App\Enums\TransactionStatus;
use App\Models\BusinessPartner;
use Illuminate\Support\Collection;

class PartnerLedgerService
{
    /** @return Collection<int, array<string, mixed>> */
    public function statementFor(BusinessPartner $partner): Collection
    {
        $entries = collect();

        if ($opening = $partner->openingBalance()->first()) {
            $effect = $opening->balance_type === PartnerOpeningBalanceType::Receivable
                ? $opening->amount_usd
                : bcmul($opening->amount_usd, '-1', 2);
            $entries->push($this->entry($opening->effective_date->toDateString(), 0, $opening->id, 'OPENING_BALANCE', __('finance.opening_balance'), null, $effect));
        }

        foreach ($partner->sales()->where('status', TransactionStatus::Confirmed->value)->with('items')->get() as $sale) {
            $entries->push($this->entry($sale->sale_date->toDateString(), 1, $sale->id, 'SALE', $sale->reference_code, $sale, $sale->total_amount));
        }

        foreach ($partner->purchases()->where('status', TransactionStatus::Confirmed->value)->with('items')->get() as $purchase) {
            $entries->push($this->entry($purchase->purchase_date->toDateString(), 2, $purchase->id, 'PURCHASE', $purchase->reference_code, $purchase, bcmul($purchase->total_amount, '-1', 2)));
        }

        foreach ($partner->dealDestinations()->whereHas('deal', fn ($query) => $query->where('status', DealStatus::Completed->value))->with('deal')->get() as $destination) {
            $entry = $this->entry($destination->deal->deal_date->toDateString(), 2, $destination->id, 'DEAL_DESTINATION', $destination->deal->reference_code, $destination->deal, $destination->amount);
            $entry['quantity_kg'] = $destination->final_quantity_kg;
            $entry['rate_per_ton'] = $destination->rate_per_ton;
            $entries->push($entry);
        }

        foreach ($partner->dealSources()->whereHas('deal', fn ($query) => $query->where('status', DealStatus::Completed->value))->with('deal')->get() as $source) {
            $entry = $this->entry($source->deal->deal_date->toDateString(), 2, $source->id, 'DEAL_SOURCE', $source->deal->reference_code, $source->deal, bcmul($source->amount, '-1', 2));
            $entry['quantity_kg'] = $source->quantity_kg;
            $entry['rate_per_ton'] = $source->rate_per_ton;
            $entries->push($entry);
        }

        $transactions = $partner->financialTransactions()
            ->where('status', FinancialTransactionStatus::Confirmed->value)
            ->whereIn('transaction_type', [FinancialTransactionType::PaymentReceived->value, FinancialTransactionType::PaymentPaid->value])
            ->get();

        foreach ($transactions as $transaction) {
            $effect = $transaction->transaction_type->partnerDirection() === 1
                ? $transaction->amount_usd
                : bcmul($transaction->amount_usd, '-1', 2);
            $entries->push($this->entry($transaction->transaction_date->toDateString(), 3, $transaction->id, $transaction->transaction_type->value, $transaction->reference_code, $transaction, $effect));
        }

        foreach ($partner->moneyMovements()
            ->where('status', MoneyMovementStatus::Confirmed->value)
            ->with(['saraf', 'account', 'deal'])
            ->get() as $movement) {
            $direction = $movement->movement_type->counterpartyDirection();
            if ($direction !== 0) {
                $effect = $direction === 1 ? $movement->amount_usd : bcmul($movement->amount_usd, '-1', 2);
                $entry = $this->entry($movement->movement_date->toDateString(), 4, $movement->id, $movement->movement_type->value, $movement->reference_code, $movement, $effect);
                $entry['related_party'] = $movement->saraf?->name;
                $entry['deal'] = $movement->deal?->reference_code;
                $entries->push($entry);
            }
        }

        $running = '0.00';

        return $entries->sortBy(fn (array $entry): string => $entry['date'].'-'.str_pad((string) $entry['order'], 2, '0', STR_PAD_LEFT).'-'.str_pad((string) $entry['id'], 10, '0', STR_PAD_LEFT))
            ->values()
            ->map(function (array $entry) use (&$running): array {
                $running = bcadd($running, $entry['effect'], 2);
                $entry['debit'] = bccomp($entry['effect'], '0', 2) > 0 ? $entry['effect'] : null;
                $entry['credit'] = bccomp($entry['effect'], '0', 2) < 0 ? bcmul($entry['effect'], '-1', 2) : null;
                $entry['running_balance'] = $running;

                return $entry;
            });
    }

    /** Collect separate receivable and payable totals from the confirmed USD statement. */
    public function paymentSummary(Collection $statement): array
    {
        $summary = array_fill_keys(['sale_receivable', 'purchase_obligation', 'received_from_customer', 'paid_to_supplier'], '0.00');
        foreach ($statement as $entry) {
            $key = match ($entry['type']) {
                'OPENING_BALANCE' => bccomp($entry['effect'], '0', 2) < 0 ? 'purchase_obligation' : 'sale_receivable',
                'SALE', 'DEAL_DESTINATION' => 'sale_receivable',
                'PURCHASE', 'DEAL_SOURCE' => 'purchase_obligation',
                'CUSTOMER_RECEIPT', 'PAYMENT_RECEIVED' => 'received_from_customer',
                'SUPPLIER_PAYMENT', 'PAYMENT_PAID' => 'paid_to_supplier',
            };
            $summary[$key] = in_array($key, ['purchase_obligation', 'received_from_customer'], true)
                ? bcsub($summary[$key], $entry['effect'], 2)
                : bcadd($summary[$key], $entry['effect'], 2);
        }
        $summary['remaining_receivable'] = bcsub($summary['sale_receivable'], $summary['received_from_customer'], 2);
        $summary['remaining_payable'] = bcsub($summary['purchase_obligation'], $summary['paid_to_supplier'], 2);

        return $summary;
    }

    public function balanceFor(BusinessPartner $partner): string
    {
        return $this->statementFor($partner)->last()['running_balance'] ?? '0.00';
    }

    public function hasConfirmedActivity(BusinessPartner $partner): bool
    {
        return $partner->sales()->where('status', TransactionStatus::Confirmed->value)->exists()
            || $partner->purchases()->where('status', TransactionStatus::Confirmed->value)->exists()
            || $partner->dealDestinations()->whereHas('deal', fn ($query) => $query->where('status', DealStatus::Completed->value))->exists()
            || $partner->dealSources()->whereHas('deal', fn ($query) => $query->where('status', DealStatus::Completed->value))->exists()
            || $partner->financialTransactions()->where('status', FinancialTransactionStatus::Confirmed->value)->exists()
            || $partner->moneyMovements()->where('status', MoneyMovementStatus::Confirmed->value)->exists();
    }

    /** @return Collection<int, array{partner: BusinessPartner, balance: string, amount: string, last_activity_date: ?string}> */
    public function positions(string $kind): Collection
    {
        return BusinessPartner::query()->with('roles')->orderBy('name')->get()
            ->map(function (BusinessPartner $partner): array {
                $statement = $this->statementFor($partner);
                $balance = $statement->last()['running_balance'] ?? '0.00';

                return [
                    'partner' => $partner,
                    'balance' => $balance,
                    'amount' => bccomp($balance, '0', 2) < 0 ? bcmul($balance, '-1', 2) : $balance,
                    'last_activity_date' => $statement->last()['date'] ?? null,
                ];
            })
            ->filter(fn (array $row): bool => $kind === 'receivable' ? bccomp($row['balance'], '0', 2) > 0 : bccomp($row['balance'], '0', 2) < 0)
            ->values();
    }

    /** @return array<string, mixed> */
    private function entry(string $date, int $order, int $id, string $type, string $reference, mixed $source, string $effect): array
    {
        return compact('date', 'order', 'id', 'type', 'reference', 'source', 'effect');
    }
}
