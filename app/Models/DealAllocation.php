<?php

namespace App\Models;

use App\Enums\DealAllocationMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['deal_id', 'deal_source_id', 'deal_destination_id', 'quantity_kg', 'allocated_cost_usd', 'allocation_method', 'created_by'])]
class DealAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:3',
            'allocated_cost_usd' => 'decimal:2',
            'allocation_method' => DealAllocationMethod::class,
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

    public function destination(): BelongsTo
    {
        return $this->belongsTo(DealDestination::class, 'deal_destination_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
