<?php

namespace App\Support;

use App\Enums\DealDestinationType;
use App\Enums\DealStatus;
use App\Models\Deal;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class RelatedDeals
{
    public const PAGE_SIZE = 25;

    public const RELATIONS = ['product', 'sources.supplier', 'sources.warehouse', 'destinations.customer', 'destinations.warehouse'];

    /** @return Builder<Deal> */
    public function query(?int $partnerId = null, string $search = ''): Builder
    {
        return Deal::query()->with(self::RELATIONS)->where('status', DealStatus::Completed->value)
            ->when($partnerId, fn (Builder $query) => $query->where(fn (Builder $party) => $party
                ->whereHas('sources', fn ($line) => $line->where('supplier_id', $partnerId))
                ->orWhereHas('destinations', fn ($line) => $line->where('customer_id', $partnerId))))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.$search.'%';
                $query->where(function (Builder $match) use ($pattern, $search): void {
                    $match->where('reference_number', 'like', $pattern);
                    foreach (self::RELATIONS as $relation) {
                        $match->orWhereHas($relation, fn ($related) => $related->where('name', 'like', $pattern));
                    }
                    if (preg_match('/^(?:DL-)?0*(\d+)$/i', $search, $id)) {
                        $match->orWhere('deals.id', (int) $id[1]);
                    }
                });
            })->latest('deal_date')->latest('id');
    }

    /** Keep an existing draft selection available even when it predates the first page. */
    public function initial(?int $partnerId, ?int $selectedId): Paginator
    {
        $query = $this->query($partnerId);
        $recent = (clone $query)->simplePaginate(self::PAGE_SIZE, page: 1);
        if ($selectedId && ! $recent->getCollection()->contains($selectedId) && ($selected = $query->find($selectedId))) {
            $recent->getCollection()->prepend($selected);
        }

        return $recent;
    }

    public static function label(Deal $deal): string
    {
        $source = $deal->sources->map(fn ($line) => $line->supplier?->name ?? $line->warehouse?->name)->filter()->unique()->implode('، ');
        $destination = $deal->destinations->map(fn ($line) => $line->customer?->name ?? $line->warehouse?->name)->filter()->unique()->implode('، ');
        $details = [$source, $destination, $deal->product?->name, app(JalaliDate::class)->format($deal->deal_date)];
        $quantity = $deal->total_delivered_quantity;
        if ($quantity !== null && $deal->destinations->isNotEmpty()) {
            $details[] = app(NumberFormat::class)->weight($quantity).' kg';
        }
        $sales = $deal->destinations->where('destination_type', DealDestinationType::Customer);
        if ($sales->isNotEmpty() && $sales->doesntContain(fn ($line) => $line->amount === null)
            && $sales->pluck('currency.value')->unique()->count() === 1) {
            $details[] = __('deals.destination_amount').' '.app(NumberFormat::class)->money($deal->total_destination_amount).' '.$sales->first()->currency->value;
        }
        $details[] = $deal->reference_code;

        return implode(' · ', array_filter($details, fn ($value) => filled($value)));
    }
}
