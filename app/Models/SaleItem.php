<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['product_id', 'warehouse_id', 'requested_quantity_kg', 'final_quantity_kg', 'rate_per_ton', 'amount'])]
class SaleItem extends Model
{
    protected function casts(): array
    {
        return [
            'requested_quantity_kg' => 'decimal:3',
            'final_quantity_kg' => 'decimal:3',
            'rate_per_ton' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasOne<InventoryMovement, $this> */
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
