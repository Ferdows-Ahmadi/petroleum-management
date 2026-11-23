<?php

namespace App\Models;

use App\Enums\WeighingMode;
use App\Enums\WeighingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_item_id', 'deal_destination_id', 'ticket_number', 'station_name', 'weighing_at', 'weighing_mode',
    'first_weight_kg', 'second_weight_kg', 'net_weight_kg',
    'vehicle_number', 'driver_name', 'ticket_image_path', 'notes',
    'status', 'created_by', 'finalized_by', 'finalized_at',
])]
class WeighingRecord extends Model
{
    protected function casts(): array
    {
        return [
            'weighing_at' => 'datetime',
            'weighing_mode' => WeighingMode::class,
            'first_weight_kg' => 'decimal:3',
            'second_weight_kg' => 'decimal:3',
            'net_weight_kg' => 'decimal:3',
            'status' => WeighingStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    /** @return BelongsTo<DealDestination, $this> */
    public function dealDestination(): BelongsTo
    {
        return $this->belongsTo(DealDestination::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function getReferenceCodeAttribute(): string
    {
        return $this->ticket_number ?: 'WG-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }
}
