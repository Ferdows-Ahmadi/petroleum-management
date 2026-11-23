<?php

namespace App\Services;

use App\Enums\CapitalTransactionStatus;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\MoneyMovementStatus;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;

class DailyJournalService
{
    public function __construct(
        private readonly FinancialAccountBalanceService $balances,
        private readonly SarafLedgerService $sarafLedger,
    ) {}

    /** @return array<string, mixed> */
    public function forAccount(FinancialAccount $account, string $date): array
    {
        $opening = $this->balances->balanceBefore($account, $date);
        $received = '0.00';
        $paid = '0.00';
        $expenses = '0.00';
        $running = $opening;

        $entries = collect();
        foreach ($account->transactions()
            ->where('status', FinancialTransactionStatus::Confirmed->value)
            ->whereDate('transaction_date', $date)
            ->with('partner')
            ->get() as $transaction) {
            $isReceipt = $transaction->transaction_type === FinancialTransactionType::PaymentReceived;
            $isExpense = $transaction->transaction_type === FinancialTransactionType::Expense;
            $entries->push([
                'sort_at' => $transaction->created_at,
                'sort_id' => $transaction->id,
                'description' => $transaction->transaction_type->label().' · '.($transaction->description ?: $transaction->reference_code),
                'party' => $transaction->partner?->name ?: ($transaction->payee ?: __('common.not_available')),
                'amount' => $transaction->amount,
                'direction' => $isReceipt ? 1 : -1,
                'kind' => $isReceipt ? 'receipt' : ($isExpense ? 'expense' : 'payment'),
                'source' => $transaction,
            ]);
        }

        foreach ($account->capitalTransactions()
            ->where('status', CapitalTransactionStatus::Confirmed->value)
            ->whereDate('transaction_date', $date)
            ->with('partner')
            ->get() as $transaction) {
            $direction = $transaction->transaction_type->accountDirection();
            $entries->push([
                'sort_at' => $transaction->created_at,
                'sort_id' => 1000000000 + $transaction->id,
                'description' => $transaction->transaction_type->label().' · '.($transaction->notes ?: $transaction->reference_code),
                'party' => $transaction->partner->name,
                'amount' => $transaction->amount_usd,
                'direction' => $direction,
                'kind' => $direction === 1 ? 'receipt' : 'payment',
                'source' => $transaction,
            ]);
        }

        foreach ($account->moneyMovements()
            ->where('status', MoneyMovementStatus::Confirmed->value)
            ->whereDate('movement_date', $date)
            ->with(['counterparty', 'deal', 'saraf'])
            ->get() as $movement) {
            $entries->push($this->moneyEntry($movement));
        }

        $entries = $entries->sortBy(fn (array $entry): string => $entry['sort_at']->format('Y-m-d H:i:s.u').'-'.str_pad((string) $entry['sort_id'], 12, '0', STR_PAD_LEFT))
            ->values()
            ->map(function (array $entry) use (&$received, &$paid, &$expenses, &$running): array {
                if ($entry['direction'] === 1) {
                    $received = bcadd($received, $entry['amount'], 2);
                    $running = bcadd($running, $entry['amount'], 2);
                } else {
                    $running = bcsub($running, $entry['amount'], 2);
                    if ($entry['kind'] === 'expense') {
                        $expenses = bcadd($expenses, $entry['amount'], 2);
                    } else {
                        $paid = bcadd($paid, $entry['amount'], 2);
                    }
                }
                $entry['running_balance'] = $running;

                return $entry;
            });

        return [
            'account' => $account,
            'date' => $date,
            'opening_balance' => $opening,
            'received' => $received,
            'paid' => $paid,
            'expenses' => $expenses,
            'closing_balance' => $running,
            'entries' => $entries,
        ];
    }

    /** @return array<string, mixed> */
    public function forSaraf(BusinessPartner $saraf, string $date): array
    {
        $opening = $this->sarafLedger->balanceBefore($saraf, $date);
        $received = '0.00';
        $paid = '0.00';
        $running = $opening;
        $entries = $saraf->sarafMoneyMovements()
            ->where('status', MoneyMovementStatus::Confirmed->value)
            ->whereDate('movement_date', $date)
            ->with(['counterparty', 'deal'])
            ->get()
            ->map(fn ($movement): array => $this->moneyEntry($movement, true))
            ->sortBy(fn (array $entry): string => $entry['sort_at']->format('Y-m-d H:i:s.u').'-'.str_pad((string) $entry['sort_id'], 12, '0', STR_PAD_LEFT))
            ->values()
            ->map(function (array $entry) use (&$received, &$paid, &$running): array {
                if ($entry['direction'] === 1) {
                    $received = bcadd($received, $entry['amount'], 2);
                    $running = bcadd($running, $entry['amount'], 2);
                } else {
                    $paid = bcadd($paid, $entry['amount'], 2);
                    $running = bcsub($running, $entry['amount'], 2);
                }
                $entry['running_balance'] = $running;

                return $entry;
            });

        return [
            'saraf' => $saraf,
            'date' => $date,
            'opening_balance' => $opening,
            'received' => $received,
            'paid' => $paid,
            'expenses' => '0.00',
            'closing_balance' => $running,
            'entries' => $entries,
        ];
    }

    /** @return array<string, mixed> */
    private function moneyEntry($movement, bool $sarafContext = false): array
    {
        $direction = $sarafContext ? $movement->movement_type->sarafDirection() : $movement->movement_type->accountDirection();

        return [
            'sort_at' => $movement->created_at,
            'sort_id' => 2000000000 + $movement->id,
            'description' => $movement->movement_type->label().' · '.($movement->notes ?: $movement->reference_code).($movement->deal ? ' · '.$movement->deal->reference_code : ''),
            'party' => $movement->counterparty?->name ?: __('common.not_available'),
            'amount' => $sarafContext ? $movement->amount_usd : $movement->amount,
            'direction' => $direction,
            'kind' => $direction === 1 ? 'receipt' : 'payment',
            'source' => $movement,
        ];
    }
}
