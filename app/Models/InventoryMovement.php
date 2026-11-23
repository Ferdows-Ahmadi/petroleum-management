<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warehouse_id', 'product_id', 'movement_type', 'quantity_kg', 'cost_amount_usd', 'occurred_at', 'purchase_item_id', 'sale_item_id', 'deal_source_id', 'deal_destination_id', 'notes'])]
class InventoryMovement extends Model
{
    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'quantity_kg' => 'decimal:3',
            'cost_amount_usd' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<PurchaseItem, $this> */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    /** @return BelongsTo<SaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function dealSource(): BelongsTo
    {
        return $this->belongsTo(DealSource::class);
    }

    public function dealDestination(): BelongsTo
    {
        return $this->belongsTo(DealDestination::class);
    }

    public function getSignedQuantityAttribute(): string
    {
        return $this->movement_type->direction() === 1 ? $this->quantity_kg : bcmul($this->quantity_kg, '-1', 3);
    }

    /** Costing and period reports use the same business date and daily order. */
    public function businessDate(): string
    {
        $date = match ($this->movement_type) {
            InventoryMovementType::PurchaseIn => $this->purchaseItem?->purchase?->purchase_date,
            InventoryMovementType::SaleOut => $this->saleItem?->sale?->sale_date,
            InventoryMovementType::DealStorageIn => $this->dealDestination?->deal?->deal_date,
            InventoryMovementType::DealStorageOut => $this->dealSource?->deal?->deal_date,
        };

        return ($date ?? $this->occurred_at)->toDateString();
    }

    public function valuationOrder(): string
    {
        return $this->businessDate().'-'.($this->movement_type->direction() === 1 ? '0' : '1').'-'.str_pad((string) $this->id, 12, '0', STR_PAD_LEFT);
    }

    public function getSourceReferenceAttribute(): string
    {
        if ($this->purchaseItem) {
            return $this->purchaseItem->purchase->reference_code;
        }

        if ($this->dealSource) {
            return $this->dealSource->deal->reference_code;
        }

        if ($this->dealDestination) {
            return $this->dealDestination->deal->reference_code;
        }

        return $this->saleItem?->sale->reference_code ?? '—';
    }
}
