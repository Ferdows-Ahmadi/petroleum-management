<?php

namespace App\Models;

use App\Enums\CommercialRecordStatus;
use App\Enums\DealQuantityAdjustmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['deal_id', 'deal_source_id', 'adjustment_type', 'quantity_kg', 'cost_amount_usd', 'reason', 'status', 'created_by', 'confirmed_by', 'confirmed_at'])]
class DealQuantityAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'adjustment_type' => DealQuantityAdjustmentType::class,
            'quantity_kg' => 'decimal:3',
            'cost_amount_usd' => 'decimal:2',
            'status' => CommercialRecordStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DealSource::class, 'deal_source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
