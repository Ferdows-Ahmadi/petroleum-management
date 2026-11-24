<?php

namespace App\Services;

use App\Enums\DealDestinationType;
use App\Enums\DealSourceType;
use App\Enums\DealStatus;
use App\Models\DealDestination;
use App\Models\DealSource;
use Illuminate\Support\Collection;

class DealTradeReportService
{
    /** @return Collection<int, array<string, mixed>> */
    public function rows(string $mode, array $filters = []): Collection
    {
        $purchase = $mode === 'purchases';
        $party = $purchase ? 'supplier' : 'customer';
        $partyId = $party.'_id';
        $stockEnd = $purchase ? 'destination' : 'source';
        $warehouseId = $filters['warehouse_id'] ?? null;
        $query = $purchase ? DealSource::query()->where('source_type', DealSourceType::Supplier->value)
            : DealDestination::query()->where('destination_type', DealDestinationType::Customer->value);
        $status = match ($filters['status'] ?? null) {
            'CONFIRMED' => [DealStatus::Completed->value],
            'DRAFT' => [DealStatus::Draft->value, DealStatus::InProgress->value],
            'CANCELLED' => [DealStatus::Cancelled->value],
            default => null,
        };

        return $query->with(['deal.product', $party])->whereHas('deal', function ($query) use ($filters, $status): void {
            $query->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('deal_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('deal_date', '<=', $date))
                ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
                ->when($status, fn ($q) => $q->whereIn('status', $status));
        })->when($filters['partner_id'] ?? null, fn ($q, $id) => $q->where($partyId, $id))
            ->when($warehouseId, fn ($q) => $q->whereHas('allocations.'.$stockEnd, fn ($end) => $end->where('warehouse_id', $warehouseId))
                ->with('allocations.'.$stockEnd.':id,warehouse_id'))
            ->get()->map(function ($line) use ($party, $purchase, $warehouseId, $stockEnd): array {
                $quantity = $purchase ? $line->quantity_kg : $line->final_quantity_kg;
                $amount = $line->amount;
                $currency = $line->currency->value;
                $splitRevenue = false;
                if ($warehouseId) {
                    $allocations = $line->allocations->where($stockEnd.'.warehouse_id', $warehouseId);
                    $quantity = $allocations->reduce(fn ($sum, $row) => bcadd($sum, $row->quantity_kg, 3), '0.000');
                    if ($purchase) {
                        $amount = $allocations->reduce(fn ($sum, $row) => bcadd($sum, $row->allocated_cost_usd, 2), '0.00');
                        $currency = 'USD';
                    } elseif ($line->final_quantity_kg === null || bccomp($quantity, $line->final_quantity_kg, 3) !== 0
                        || $allocations->count() !== $line->allocations->count()) {
                        // Allocations store purchase costs. Sale proceeds exist only on the full delivery.
                        $amount = null;
                        $splitRevenue = true;
                    }
                }

                return ['deal' => $line->deal, 'party' => $line->{$party}, 'quantity' => $quantity, 'amount' => $amount,
                    'currency' => $currency, 'split_revenue' => $splitRevenue, 'confirmed' => $line->deal->status === DealStatus::Completed];
            })->sortByDesc(fn ($row) => $row['deal']->deal_date->toDateString().'-'.str_pad((string) $row['deal']->id, 12, '0', STR_PAD_LEFT))->values();
    }
}
