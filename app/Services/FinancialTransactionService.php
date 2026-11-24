<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialTransactionService
{
    public function __construct(private readonly CurrencyConversionService $conversion) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, FinancialTransactionType $type, int $userId): FinancialTransaction
    {
        return DB::transaction(function () use ($data, $type, $userId): FinancialTransaction {
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($data['financial_account_id']);
            $normalized = $this->normalize($data, $type, $account);

            return FinancialTransaction::create([
                ...$normalized,
                'transaction_type' => $type,
                'status' => FinancialTransactionStatus::PendingApproval,
                'created_by' => $userId,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(FinancialTransaction $transaction, array $data): FinancialTransaction
    {
        return DB::transaction(function () use ($transaction, $data): FinancialTransaction {
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($data['financial_account_id']);
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $this->ensureDraft($locked);
            $locked->update($this->normalize($data, $locked->transaction_type, $account));

            return $locked->refresh();
        });
    }

    public function confirm(FinancialTransaction $transaction, int $userId): bool
    {
        return DB::transaction(function () use ($transaction, $userId): bool {
            $accountId = FinancialTransaction::query()->whereKey($transaction->id)->firstOrFail(['financial_account_id'])->financial_account_id;
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($accountId);
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($locked->status === FinancialTransactionStatus::Confirmed) {
                return false;
            }
            if (! $locked->status->awaitsConfirmation()) {
                throw ValidationException::withMessages(['status' => __('common.withdrawn_approval')]);
            }

            $this->ensureAccountCompatible($account, $locked->currency);
            $amountUsd = $this->conversion->toUsd($locked->amount, $locked->currency, $locked->exchange_rate_to_usd);
            $locked->update([
                'amount_usd' => $amountUsd,
                'status' => FinancialTransactionStatus::Confirmed,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            return true;
        });
    }

    public function delete(FinancialTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status === FinancialTransactionStatus::PendingApproval) {
                $locked->update(['status' => FinancialTransactionStatus::Cancelled]);

                return;
            }
            if ($locked->status === FinancialTransactionStatus::Cancelled) {
                return;
            }

            $this->ensureDraft($locked);
            $locked->delete();
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, FinancialTransactionType $type, FinancialAccount $account): array
    {
        $currency = Currency::from($data['currency']);
        $this->ensureAccountCompatible($account, $currency);

        if ($type !== FinancialTransactionType::Expense && empty($data['business_partner_id'])) {
            throw ValidationException::withMessages(['business_partner_id' => __('finance.errors.partner_required')]);
        }

        return [
            ...Arr::except($data, ['confirm_now', 'status', 'amount_usd', 'transaction_type', 'confirmed_by', 'confirmed_at']),
            'business_partner_id' => $type === FinancialTransactionType::Expense ? null : $data['business_partner_id'],
            'expense_category' => $type === FinancialTransactionType::Expense ? $data['expense_category'] : null,
            'exchange_rate_to_usd' => $currency === Currency::Afn ? $data['exchange_rate_to_usd'] : null,
            'amount_usd' => $this->conversion->toUsd($data['amount'], $currency, $data['exchange_rate_to_usd'] ?? null),
        ];
    }

    private function ensureAccountCompatible(FinancialAccount $account, Currency $currency): void
    {
        if (! $account->is_active) {
            throw ValidationException::withMessages(['financial_account_id' => __('finance.errors.account_inactive')]);
        }

        if ($account->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => __('finance.errors.currency_mismatch')]);
        }
    }

    private function ensureDraft(FinancialTransaction $transaction): void
    {
        if ($transaction->status !== FinancialTransactionStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('finance.errors.confirmed_immutable')]);
        }
    }
}
