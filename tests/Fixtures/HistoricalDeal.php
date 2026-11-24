<?php

namespace Tests\Fixtures;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Services\DealService;
use Illuminate\Support\Arr;

final class HistoricalDeal
{
    /** Reproduce a pre-upgrade single-line draft, including empty line placeholders. */
    public static function draft(array $data, int $userId): Deal
    {
        $deal = Deal::create([
            ...Arr::only($data, ['deal_date', 'product_id', 'notes']),
            'status' => DealStatus::Draft,
            'created_by' => $userId,
        ]);
        $deal->update(['reference_number' => sprintf('DL-%06d', $deal->id)]);
        $deal->sources()->create(['source_type' => 'SUPPLIER', 'currency' => 'USD']);
        $deal->destinations()->create(['destination_type' => 'CUSTOMER', 'currency' => 'USD']);
        $service = app(DealService::class);
        if (isset($data['supplier_id'])) {
            $service->addSource($deal, [
                'source_type' => 'SUPPLIER', 'supplier_id' => $data['supplier_id'],
                'quantity_kg' => $data['source_quantity_kg'], 'rate_per_ton' => $data['source_rate_per_ton'],
            ]);
        }
        if (isset($data['customer_id'])) {
            $service->addDestination($deal, [
                'destination_type' => 'CUSTOMER', 'customer_id' => $data['customer_id'],
                'final_quantity_kg' => $data['final_quantity_kg'], 'rate_per_ton' => $data['destination_rate_per_ton'],
            ]);
        }

        return $deal;
    }
}
