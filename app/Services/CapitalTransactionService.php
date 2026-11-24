<?php

namespace App\Services;

use App\Enums\CapitalTransactionStatus;
use App\Enums\CapitalTransactionType;
use App\Enums\Currency;
use App\Models\CapitalTransaction;
use App\Models\FinancialAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CapitalTransactionService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): CapitalTransaction
    {
        return DB::transaction(function () use ($data, $userId): CapitalTransaction {
            $this->lockAndValidateAccount($data);

            return CapitalTransaction::create([
                ...$this->normalize($data),
                'status' => CapitalTransactionStatus::PendingApproval,
                'created_by' => $userId,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CapitalTransaction $transaction, array $data): CapitalTransaction
    {
        return DB::transaction(function () use ($transaction, $data): CapitalTransaction {
            $this->lockAndValidateAccount($data);
            $locked = CapitalTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $this->ensureDraft($locked);
            $locked->update($this->normalize($data));

            return $locked->refresh();
        });
    }

    public function confirm(CapitalTransaction $transaction, int $userId): bool
    {
        return DB::transaction(function () use ($transaction, $userId): bool {
            $accountId = CapitalTransaction::query()->whereKey($transaction->id)->value('financial_account_id');
            if ($accountId) {
                FinancialAccount::query()->lockForUpdate()->findOrFail($accountId);
            }
            $locked = CapitalTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status === CapitalTransactionStatus::Confirmed) {
                return false;
            }
            if (! $locked->status->awaitsConfirmation()) {
                throw ValidationException::withMessages(['status' => __('common.withdrawn_approval')]);
            }

            $this->validateAccountFor($locked->transaction_type, $locked->financial_account_id);
            $locked->update([
                'status' => CapitalTransactionStatus::Confirmed,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            return true;
        });
    }

    public function delete(CapitalTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $locked = CapitalTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status === CapitalTransactionStatus::PendingApproval) {
                $locked->update(['status' => CapitalTransactionStatus::Cancelled]);

                return;
            }
            if ($locked->status === CapitalTransactionStatus::Cancelled) {
                return;
            }

            $this->ensureDraft($locked);
            $locked->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function lockAndValidateAccount(array $data): void
    {
        $type = CapitalTransactionType::from($data['transaction_type']);
        $accountId = $data['financial_account_id'] ?? null;
        if ($accountId) {
            FinancialAccount::query()->lockForUpdate()->findOrFail($accountId);
        }
        $this->validateAccountFor($type, $accountId ? (int) $accountId : null);
    }

    private function validateAccountFor(CapitalTransactionType $type, ?int $accountId): void
    {
        if ($type->isCashMovement() && ! $accountId) {
            throw ValidationException::withMessages(['financial_account_id' => __('capital.errors.account_required')]);
        }
        if (! $type->isCashMovement() && $accountId) {
            throw ValidationException::withMessages(['financial_account_id' => __('capital.errors.account_not_allowed')]);
        }
        if ($accountId) {
            $account = FinancialAccount::query()->findOrFail($accountId);
            if (! $account->is_active || $account->currency !== Currency::Usd) {
                throw ValidationException::withMessages(['financial_account_id' => __('capital.errors.usd_account_required')]);
            }
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $type = CapitalTransactionType::from($data['transaction_type']);

        return [
            ...Arr::except($data, ['status', 'confirmed_by', 'confirmed_at']),
            'financial_account_id' => $type->isCashMovement() ? $data['financial_account_id'] : null,
            'adjustment_direction' => $type === CapitalTransactionType::Adjustment ? $data['adjustment_direction'] : null,
        ];
    }

    private function ensureDraft(CapitalTransaction $transaction): void
    {
        if ($transaction->status !== CapitalTransactionStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('capital.errors.confirmed_immutable')]);
        }
    }
}
