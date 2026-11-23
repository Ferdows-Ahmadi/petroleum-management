<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DealSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['deal_id', 'source_type', 'supplier_id', 'warehouse_id', 'quantity_kg', 'rate_per_ton', 'amount', 'currency', 'reference_number', 'notes'])]
class DealSource extends Model
{
    protected function casts(): array
    {
        return ['source_type' => DealSourceType::class, 'quantity_kg' => 'decimal:3', 'rate_per_ton' => 'decimal:4', 'amount' => 'decimal:2', 'currency' => Currency::class];
    }

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<BusinessPartner, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'supplier_id');
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
}
