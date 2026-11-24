<?php

namespace App\Services;

use App\Enums\DealStatus;
use App\Enums\TransactionStatus;
use App\Enums\WeighingMode;
use App\Enums\WeighingStatus;
use App\Models\Deal;
use App\Models\DealDestination;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\WeighingRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class WeighingService
{
    public function __construct(private readonly PetroleumAmountCalculator $amountCalculator) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): WeighingRecord
    {
        return DB::transaction(function () use ($data, $userId): WeighingRecord {
            [$target, $targetField] = $this->lockTarget($data);

            if ($target->weighingRecord()->exists()) {
                throw ValidationException::withMessages([$targetField => __('weighings.errors.duplicate')]);
            }

            $mode = WeighingMode::from($data['weighing_mode'] ?? WeighingMode::TwoStage->value);
            $net = $this->resolveDraftNet($mode, $data);

            return $target->weighingRecord()->create([
                ...Arr::except($data, ['ticket_image', 'net_weight_kg', 'status', 'sale_item_id', 'deal_destination_id']),
                'weighing_mode' => $mode,
                'first_weight_kg' => $mode === WeighingMode::TwoStage ? $data['first_weight_kg'] : null,
                'second_weight_kg' => $mode === WeighingMode::TwoStage ? ($data['second_weight_kg'] ?? null) : null,
                'net_weight_kg' => $net,
                'status' => WeighingStatus::Draft,
                'created_by' => $userId,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(WeighingRecord $record, array $data): WeighingRecord
    {
        return DB::transaction(function () use ($record, $data): WeighingRecord {
            $locked = WeighingRecord::query()->lockForUpdate()->findOrFail($record->id);
            $this->lockRecordParent($locked);
            $this->ensureDraft($locked);

            $mode = WeighingMode::from($data['weighing_mode'] ?? $locked->weighing_mode->value);
            $locked->update([
                ...Arr::except($data, ['sale_item_id', 'deal_destination_id', 'ticket_image', 'net_weight_kg', 'status']),
                'weighing_mode' => $mode,
                'first_weight_kg' => $mode === WeighingMode::TwoStage ? $data['first_weight_kg'] : null,
                'second_weight_kg' => $mode === WeighingMode::TwoStage ? ($data['second_weight_kg'] ?? null) : null,
                'net_weight_kg' => $this->resolveDraftNet($mode, $data),
            ]);

            return $locked->refresh();
        });
    }

    public function finalize(WeighingRecord $record, int $userId): bool
    {
        return DB::transaction(function () use ($record, $userId): bool {
            $locked = WeighingRecord::query()->lockForUpdate()->findOrFail($record->id);

            if ($locked->status === WeighingStatus::Finalized) {
                return false;
            }

            $item = $this->lockRecordParent($locked);
            $net = $this->finalNet($locked);
            try {
                $amount = $this->amountCalculator->calculate($net, $item->rate_per_ton);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['first_weight_kg' => __('trades.errors.amount_exceeds_precision')]);
            }

            $locked->update([
                'net_weight_kg' => $net,
                'status' => WeighingStatus::Finalized,
                'finalized_by' => $userId,
                'finalized_at' => now(),
            ]);
            $item->update([
                'final_quantity_kg' => $net,
                'amount' => $amount,
            ]);

            if ($item instanceof SaleItem) {
                // The parent sale is locked by lockRecordParent. A multi-line sale
                // is ready for approval only after the last ticket is finalized.
                Sale::query()->whereKey($item->sale_id)
                    ->where('status', TransactionStatus::AwaitingWeighing->value)
                    ->whereDoesntHave('items', fn ($lines) => $lines->whereDoesntHave(
                        'weighingRecord',
                        fn ($tickets) => $tickets->where('status', WeighingStatus::Finalized->value),
                    ))
                    ->update(['status' => TransactionStatus::PendingApproval]);
            }

            return true;
        });
    }

    public function completeSecondWeight(WeighingRecord $record, string|int|float $secondWeight): WeighingRecord
    {
        return DB::transaction(function () use ($record, $secondWeight): WeighingRecord {
            $locked = WeighingRecord::query()->lockForUpdate()->findOrFail($record->id);
            $this->lockRecordParent($locked);
            $this->ensureDraft($locked);
            if ($locked->weighing_mode !== WeighingMode::TwoStage) {
                throw ValidationException::withMessages(['weighing_mode' => __('weighings.errors.second_only_two_stage')]);
            }
            $locked->update([
                'second_weight_kg' => (string) $secondWeight,
                'net_weight_kg' => $this->calculateNet($locked->first_weight_kg, $secondWeight),
            ]);

            return $locked->refresh();
        });
    }

    public function replaceDraftTicketPath(WeighingRecord $record, string $path): WeighingRecord
    {
        return DB::transaction(function () use ($record, $path): WeighingRecord {
            $locked = WeighingRecord::query()->lockForUpdate()->findOrFail($record->id);
            $this->lockRecordParent($locked);
            $this->ensureDraft($locked);
            $locked->update(['ticket_image_path' => $path]);

            return $locked->refresh();
        });
    }

    public function calculateNetIfComplete(string|int|float $firstWeight, string|int|float|null $secondWeight): ?string
    {
        if ($secondWeight === null || $secondWeight === '') {
            return null;
        }

        return $this->calculateNet($firstWeight, $secondWeight);
    }

    public function calculateNet(string|int|float $firstWeight, string|int|float $secondWeight): string
    {
        $first = (string) $firstWeight;
        $second = (string) $secondWeight;

        if (bccomp($first, '0', 3) <= 0 || bccomp($second, '0', 3) <= 0 || bccomp($first, $second, 3) <= 0) {
            throw ValidationException::withMessages(['first_weight_kg' => __('weighings.errors.first_must_exceed_second')]);
        }

        return bcsub($first, $second, 3);
    }

    /** @param array<string, mixed> $data */
    private function resolveDraftNet(WeighingMode $mode, array $data): ?string
    {
        if ($mode === WeighingMode::DirectNet) {
            $net = (string) ($data['net_weight_kg'] ?? '');
            if ($net === '' || bccomp($net, '0', 3) <= 0) {
                throw ValidationException::withMessages(['net_weight_kg' => __('weighings.errors.net_required')]);
            }

            return bcadd($net, '0', 3);
        }

        return $this->calculateNetIfComplete($data['first_weight_kg'], $data['second_weight_kg'] ?? null);
    }

    private function finalNet(WeighingRecord $record): string
    {
        if ($record->weighing_mode === WeighingMode::DirectNet) {
            if ($record->net_weight_kg === null || bccomp($record->net_weight_kg, '0', 3) <= 0) {
                throw ValidationException::withMessages(['net_weight_kg' => __('weighings.errors.net_required')]);
            }

            return bcadd($record->net_weight_kg, '0', 3);
        }

        if ($record->second_weight_kg === null) {
            throw ValidationException::withMessages(['second_weight_kg' => __('office.errors.second_weight_required')]);
        }

        return $this->calculateNet($record->first_weight_kg, $record->second_weight_kg);
    }

    private function ensureSaleAllowsWeighing(Sale $sale): void
    {
        if (! $sale->status->allowsWeighing()) {
            throw ValidationException::withMessages(['sale_item_id' => __('weighings.errors.sale_not_draft')]);
        }
    }

    private function ensureDealEditable(Deal $deal): void
    {
        if (! in_array($deal->status, [DealStatus::Draft, DealStatus::InProgress], true)) {
            throw ValidationException::withMessages(['deal_destination_id' => __('deals.errors.not_completable')]);
        }
    }

    /** @param array<string, mixed> $data
     * @return array{SaleItem|DealDestination, string}
     */
    private function lockTarget(array $data): array
    {
        if (! empty($data['deal_destination_id'])) {
            $dealId = DealDestination::query()->whereKey($data['deal_destination_id'])->value('deal_id');
            $deal = Deal::query()->lockForUpdate()->findOrFail($dealId);
            $this->ensureDealEditable($deal);
            $destination = DealDestination::query()->where('deal_id', $deal->id)->lockForUpdate()->findOrFail($data['deal_destination_id']);
            if ($deal->status === DealStatus::Draft) {
                $deal->update(['status' => DealStatus::InProgress]);
            }

            return [$destination, 'deal_destination_id'];
        }

        $saleId = SaleItem::query()->whereKey($data['sale_item_id'])->value('sale_id');
        $sale = Sale::query()->lockForUpdate()->findOrFail($saleId);
        $this->ensureSaleAllowsWeighing($sale);

        return [SaleItem::query()->where('sale_id', $sale->id)->lockForUpdate()->findOrFail($data['sale_item_id']), 'sale_item_id'];
    }

    private function lockRecordParent(WeighingRecord $record): SaleItem|DealDestination
    {
        if ($record->deal_destination_id) {
            $dealId = DealDestination::query()->whereKey($record->deal_destination_id)->value('deal_id');
            $deal = Deal::query()->lockForUpdate()->findOrFail($dealId);
            $this->ensureDealEditable($deal);

            return DealDestination::query()->where('deal_id', $deal->id)->lockForUpdate()->findOrFail($record->deal_destination_id);
        }

        $saleId = SaleItem::query()->whereKey($record->sale_item_id)->value('sale_id');
        $sale = Sale::query()->lockForUpdate()->findOrFail($saleId);
        $this->ensureSaleAllowsWeighing($sale);

        return SaleItem::query()->where('sale_id', $sale->id)->lockForUpdate()->findOrFail($record->sale_item_id);
    }

    private function ensureDraft(WeighingRecord $record): void
    {
        if ($record->status !== WeighingStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('weighings.errors.finalized_immutable')]);
        }
    }
}
