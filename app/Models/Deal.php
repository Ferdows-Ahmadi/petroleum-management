<?php

namespace App\Models;

use App\Enums\DealDestinationType;
use App\Enums\DealSourceType;
use App\Enums\DealStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['reference_number', 'deal_date', 'requested_delivery_date', 'requested_quantity_kg', 'product_id', 'status', 'notes', 'created_by', 'completed_by', 'completed_at'])]
class Deal extends Model
{
    protected function casts(): array
    {
        return [
            'deal_date' => 'date',
            'requested_delivery_date' => 'date',
            'requested_quantity_kg' => 'decimal:3',
            'status' => DealStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<DealSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(DealSource::class);
    }

    /** @return HasMany<DealDestination, $this> */
    public function destinations(): HasMany
    {
        return $this->hasMany(DealDestination::class);
    }

    /** @return HasMany<MoneyMovement, $this> */
    public function moneyMovements(): HasMany
    {
        return $this->hasMany(MoneyMovement::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DealAllocation::class);
    }

    public function quantityAdjustments(): HasMany
    {
        return $this->hasMany(DealQuantityAdjustment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(DealExpense::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(DealSettlement::class);
    }

    public function latestSettlement(): HasOne
    {
        return $this->hasOne(DealSettlement::class)->ofMany('revision', 'max');
    }

    public function latestConfirmedSettlement(): HasOne
    {
        return $this->hasOne(DealSettlement::class)->ofMany(['revision' => 'max'], fn ($query) => $query->where('status', 'CONFIRMED'));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getReferenceCodeAttribute(): string
    {
        return $this->reference_number ?: 'DL-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function getTotalSourceAmountAttribute(): string
    {
        return $this->sources->where('source_type', DealSourceType::Supplier)
            ->reduce(fn (string $total, DealSource $source): string => bcadd($total, $source->amount ?? '0.00', 2), '0.00');
    }

    public function getTotalDestinationAmountAttribute(): string
    {
        return $this->destinations->where('destination_type', DealDestinationType::Customer)
            ->reduce(fn (string $total, DealDestination $destination): string => bcadd($total, $destination->amount ?? '0.00', 2), '0.00');
    }

    public function getTotalSourceCostAttribute(): ?string
    {
        return $this->sources->contains(fn ($source) => $source->amount === null) ? null
            : $this->sources->reduce(fn ($sum, $source) => bcadd($sum, $source->amount, 2), '0.00');
    }

    public function getTotalDeliveredQuantityAttribute(): ?string
    {
        return $this->destinations->contains(fn ($destination) => $destination->final_quantity_kg === null) ? null
            : $this->destinations->reduce(fn ($sum, $destination) => bcadd($sum, $destination->final_quantity_kg, 3), '0.000');
    }
}
