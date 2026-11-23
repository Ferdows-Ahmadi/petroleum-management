<?php

namespace App\Services;

use App\Enums\CapitalTransactionStatus;
use App\Enums\FinancialTransactionStatus;
use App\Enums\MoneyMovementStatus;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Builder;

class FinancialAccountBalanceService
{
    public function balanceFor(FinancialAccount $account, ?string $throughDate = null): string
    {
        $balance = $throughDate === null || $account->opening_balance_date->toDateString() <= $throughDate
            ? $account->opening_balance
            : '0.00';

        $transactions = $account->transactions()
            ->where('status', FinancialTransactionStatus::Confirmed->value)
            ->when($throughDate, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $throughDate))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            $balance = $transaction->transaction_type->accountDirection() === 1
                ? bcadd($balance, $transaction->amount, 2)
                : bcsub($balance, $transaction->amount, 2);
        }

        $moneyMovements = $account->moneyMovements()
            ->where('status', MoneyMovementStatus::Confirmed->value)
            ->when($throughDate, fn (Builder $query) => $query->whereDate('movement_date', '<=', $throughDate))
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        foreach ($moneyMovements as $movement) {
            $balance = $movement->movement_type->accountDirection() === 1
                ? bcadd($balance, $movement->amount, 2)
                : bcsub($balance, $movement->amount, 2);
        }

        $capitalTransactions = $account->capitalTransactions()
            ->where('status', CapitalTransactionStatus::Confirmed->value)
            ->when($throughDate, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $throughDate))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($capitalTransactions as $transaction) {
            $balance = $transaction->transaction_type->accountDirection() === 1
                ? bcadd($balance, $transaction->amount_usd, 2)
                : bcsub($balance, $transaction->amount_usd, 2);
        }

        return $balance;
    }

    public function balanceBefore(FinancialAccount $account, string $date): string
    {
        $balance = $account->opening_balance_date->toDateString() <= $date ? $account->opening_balance : '0.00';
        $transactions = $account->transactions()
            ->where('status', FinancialTransactionStatus::Confirmed->value)
            ->whereDate('transaction_date', '<', $date)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            $balance = $transaction->transaction_type->accountDirection() === 1
                ? bcadd($balance, $transaction->amount, 2)
                : bcsub($balance, $transaction->amount, 2);
        }

        $moneyMovements = $account->moneyMovements()
            ->where('status', MoneyMovementStatus::Confirmed->value)
            ->whereDate('movement_date', '<', $date)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        foreach ($moneyMovements as $movement) {
            $balance = $movement->movement_type->accountDirection() === 1
                ? bcadd($balance, $movement->amount, 2)
                : bcsub($balance, $movement->amount, 2);
        }

        $capitalTransactions = $account->capitalTransactions()
            ->where('status', CapitalTransactionStatus::Confirmed->value)
            ->whereDate('transaction_date', '<', $date)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($capitalTransactions as $transaction) {
            $balance = $transaction->transaction_type->accountDirection() === 1
                ? bcadd($balance, $transaction->amount_usd, 2)
                : bcsub($balance, $transaction->amount_usd, 2);
        }

        return $balance;
    }
}
