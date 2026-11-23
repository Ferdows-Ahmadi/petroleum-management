<?php

namespace App\Services;

use App\Enums\CommercialRecordStatus;
use App\Enums\Currency;
use App\Enums\DealQuantityAdjustmentType;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Models\Deal;
use App\Models\DealExpense;
use App\Models\DealQuantityAdjustment;
use App\Models\DealSource;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealCommercialRecordService
{
    public function __construct(
        private readonly CurrencyConversionService $conversion,
        private readonly PetroleumAmountCalculator $amounts,
    ) {}

    /** @param array<string,mixed> $data */
    public function createAdjustment(Deal $deal, array $data, int $userId): DealQuantityAdjustment
    {
        return DB::transaction(function () use ($deal, $data, $userId): DealQuantityAdjustment {
            $deal = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $this->ensureDealMutable($deal);
            $source = DealSource::query()->where('deal_id', $deal->id)->findOrFail($data['deal_source_id']);
            $quantity = bcadd((string) $data['quantity_kg'], '0', 3);
            $type = DealQuantityAdjustmentType::from($data['adjustment_type']);
            $cost = $type === DealQuantityAdjustmentType::Shortage
                ? $this->amounts->calculate($quantity, (string) $source->rate_per_ton)
                : '0.00';

            return $deal->quantityAdjustments()->create([
                'deal_source_id' => $source->id,
                'adjustment_type' => $type,
                'quantity_kg' => $quantity,
                'cost_amount_usd' => $cost,
                'reason' => $data['reason'],
                'status' => CommercialRecordStatus::Draft,
                'created_by' => $userId,
            ]);
        });
    }

    /** @param array<string,mixed> $data */
    public function createExpense(Deal $deal, array $data, int $userId): DealExpense
    {
        return DB::transaction(function () use ($deal, $data, $userId): DealExpense {
            $deal = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $this->ensureDealMutable($deal);
            $currency = Currency::from($data['currency']);
            $amountUsd = $this->conversion->toUsd((string) $data['amount'], $currency, $data['exchange_rate_to_usd'] ?? null);
            if (! empty($data['financial_transaction_id'])) {
                $payment = FinancialTransaction::query()->lockForUpdate()->findOrFail($data['financial_transaction_id']);
                if ($payment->transaction_type !== FinancialTransactionType::Expense || $payment->status !== FinancialTransactionStatus::Confirmed
                    || bccomp($payment->amount_usd, $amountUsd, 2) !== 0 || DealExpense::query()->where('financial_transaction_id', $payment->id)->exists()) {
                    throw ValidationException::withMessages(['financial_transaction_id' => __('settlements.errors.invalid_expense_payment')]);
                }
            }

            return $deal->expenses()->create([
                ...$data,
                'currency' => $currency,
                'exchange_rate_to_usd' => $currency === Currency::Afn ? $data['exchange_rate_to_usd'] : null,
                'amount_usd' => $amountUsd,
                'status' => CommercialRecordStatus::Draft,
                'created_by' => $userId,
            ]);
        });
    }

    public function confirmAdjustment(DealQuantityAdjustment $adjustment, int $userId): bool
    {
        return $this->confirm($adjustment, $userId);
    }

    public function confirmExpense(DealExpense $expense, int $userId): bool
    {
        return $this->confirm($expense, $userId);
    }

    public function deleteAdjustment(DealQuantityAdjustment $adjustment): void
    {
        $this->deleteDraft($adjustment);
    }

    public function deleteExpense(DealExpense $expense): void
    {
        $this->deleteDraft($expense);
    }

    private function confirm(DealQuantityAdjustment|DealExpense $record, int $userId): bool
    {
        return DB::transaction(function () use ($record, $userId): bool {
            $deal = Deal::query()->lockForUpdate()->findOrFail($record->deal_id);
            $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            if ($locked->status === CommercialRecordStatus::Confirmed) {
                return false;
            }
            $this->ensureDealMutable($deal);
            $locked->update(['status' => CommercialRecordStatus::Confirmed, 'confirmed_by' => $userId, 'confirmed_at' => now()]);

            return true;
        });
    }

    private function deleteDraft(DealQuantityAdjustment|DealExpense $record): void
    {
        DB::transaction(function () use ($record): void {
            $deal = Deal::query()->lockForUpdate()->findOrFail($record->deal_id);
            $this->ensureDealMutable($deal);
            $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            if ($locked->status !== CommercialRecordStatus::Draft) {
                throw ValidationException::withMessages(['status' => __('settlements.errors.confirmed_immutable')]);
            }
            $locked->delete();
        });
    }

    private function ensureDealMutable(Deal $deal): void
    {
        if ($deal->settlements()->latest('revision')->first()?->status === CommercialRecordStatus::Confirmed) {
            throw ValidationException::withMessages(['status' => __('settlements.errors.confirmed_immutable')]);
        }
    }
}
