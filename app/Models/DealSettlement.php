<?php

namespace App\Models;

use App\Enums\CommercialRecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['deal_id', 'revision', 'status', 'source_quantity_kg', 'customer_quantity_kg', 'storage_in_quantity_kg', 'storage_out_quantity_kg', 'shortage_quantity_kg', 'excess_quantity_kg', 'unexplained_quantity_kg', 'source_value_usd', 'sale_revenue_usd', 'direct_expenses_usd', 'shortage_cost_usd', 'final_result_usd', 'snapshot', 'notes', 'created_by', 'confirmed_by', 'confirmed_at'])]
class DealSettlement extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CommercialRecordStatus::class,
            'source_quantity_kg' => 'decimal:3',
            'customer_quantity_kg' => 'decimal:3',
            'storage_in_quantity_kg' => 'decimal:3',
            'storage_out_quantity_kg' => 'decimal:3',
            'shortage_quantity_kg' => 'decimal:3',
            'excess_quantity_kg' => 'decimal:3',
            'unexplained_quantity_kg' => 'decimal:3',
            'source_value_usd' => 'decimal:2',
            'sale_revenue_usd' => 'decimal:2',
            'direct_expenses_usd' => 'decimal:2',
            'shortage_cost_usd' => 'decimal:2',
            'final_result_usd' => 'decimal:2',
            'snapshot' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
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
