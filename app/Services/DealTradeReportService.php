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
            ->when($filters['warehouse_id'] ?? null, fn ($q) => $q->whereRaw('1 = 0'))
            ->get()->map(fn ($line): array => [
                'deal' => $line->deal, 'party' => $line->{$party},
                'quantity' => ($purchase ? $line->quantity_kg : $line->final_quantity_kg) ?? '0.000',
                'amount' => $line->amount ?? '0.00', 'confirmed' => $line->deal->status === DealStatus::Completed,
            ])->sortByDesc(fn ($row) => $row['deal']->deal_date->toDateString().'-'.str_pad((string) $row['deal']->id, 12, '0', STR_PAD_LEFT))->values();
    }
}
