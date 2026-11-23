<?php

namespace App\Services;

use App\Enums\CapitalTransactionStatus;
use App\Models\CapitalPartner;
use Illuminate\Support\Collection;

class CapitalBalanceService
{
    /** @return Collection<int, array<string, mixed>> */
    public function statementFor(CapitalPartner $partner, ?string $from = null, ?string $to = null): Collection
    {
        $running = '0.00';

        return $partner->transactions()
            ->where('status', CapitalTransactionStatus::Confirmed->value)
            ->when($from, fn ($query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($to, fn ($query, string $date) => $query->whereDate('transaction_date', '<=', $date))
            ->with('account')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function ($transaction) use (&$running): array {
                $direction = $transaction->transaction_type->capitalDirection($transaction->adjustment_direction);
                $effect = $direction === 1 ? $transaction->amount_usd : bcmul($transaction->amount_usd, '-1', 2);
                $running = bcadd($running, $effect, 2);

                return [
                    'transaction' => $transaction,
                    'effect' => $effect,
                    'increase' => $direction === 1 ? $transaction->amount_usd : null,
                    'decrease' => $direction === -1 ? $transaction->amount_usd : null,
                    'running_balance' => $running,
                ];
            });
    }

    public function balanceFor(CapitalPartner $partner): string
    {
        return $this->statementFor($partner)->last()['running_balance'] ?? '0.00';
    }

    /** @return array{initial: string, increases: string, decreases: string, balance: string} */
    public function summaryFor(CapitalPartner $partner, ?string $from = null, ?string $to = null): array
    {
        $initial = '0.00';
        $increases = '0.00';
        $decreases = '0.00';

        foreach ($this->statementFor($partner, $from, $to) as $entry) {
            $transaction = $entry['transaction'];
            if ($transaction->transaction_type->value === 'INITIAL_CAPITAL') {
                $initial = bcadd($initial, $transaction->amount_usd, 2);
            } elseif ($entry['increase'] !== null) {
                $increases = bcadd($increases, $entry['increase'], 2);
            } else {
                $decreases = bcadd($decreases, $entry['decrease'], 2);
            }
        }

        return compact('initial', 'increases', 'decreases') + ['balance' => bcadd(bcadd($initial, $increases, 2), bcmul($decreases, '-1', 2), 2)];
    }
}
