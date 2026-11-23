<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_partner_id', 'sale_date', 'reference_number', 'currency', 'status', 'notes', 'created_by', 'confirmed_by', 'confirmed_at'])]
class Sale extends Model
{
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'currency' => Currency::class,
            'status' => TransactionStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BusinessPartner, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'business_partner_id');
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function getReferenceCodeAttribute(): string
    {
        return 'SL-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function getTotalAmountAttribute(): string
    {
        return $this->items->reduce(
            fn (string $total, SaleItem $item): string => bcadd($total, $item->amount, 2),
            '0.00',
        );
    }

    public function getTotalFinalQuantityKgAttribute(): string
    {
        return $this->items->reduce(
            fn (string $total, SaleItem $item): string => bcadd($total, $item->final_quantity_kg ?? '0.000', 3),
            '0.000',
        );
    }
}
