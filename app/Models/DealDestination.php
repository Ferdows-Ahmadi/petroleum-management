<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DealDestinationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['deal_id', 'destination_type', 'customer_id', 'warehouse_id', 'requested_quantity_kg', 'final_quantity_kg', 'rate_per_ton', 'amount', 'currency', 'reference_number', 'notes'])]
class DealDestination extends Model
{
    protected function casts(): array
    {
        return [
            'destination_type' => DealDestinationType::class,
            'requested_quantity_kg' => 'decimal:3',
            'final_quantity_kg' => 'decimal:3',
            'rate_per_ton' => 'decimal:4',
            'amount' => 'decimal:2',
            'currency' => Currency::class,
        ];
    }

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<BusinessPartner, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DealAllocation::class);
    }

    public function inventoryMovement(): HasOne
    {
        return $this->hasOne(InventoryMovement::class);
    }

    /** @return HasOne<WeighingRecord, $this> */
    public function weighingRecord(): HasOne
    {
        return $this->hasOne(WeighingRecord::class);
    }
}
