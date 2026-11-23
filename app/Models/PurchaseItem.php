<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['product_id', 'warehouse_id', 'quantity_kg', 'rate_per_ton', 'amount'])]
class PurchaseItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:3',
            'rate_per_ton' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
}
