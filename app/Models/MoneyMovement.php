<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\MoneyMovementStatus;
use App\Enums\MoneyMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'movement_date', 'movement_type', 'counterparty_id', 'saraf_id', 'deal_id',
    'financial_account_id', 'amount', 'currency', 'exchange_rate_to_usd', 'amount_usd',
    'reference', 'notes', 'status', 'created_by', 'confirmed_by', 'confirmed_at',
])]
class MoneyMovement extends Model
{
    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'movement_type' => MoneyMovementType::class,
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'exchange_rate_to_usd' => 'decimal:6',
            'amount_usd' => 'decimal:2',
            'status' => MoneyMovementStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'counterparty_id');
    }

    public function saraf(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'saraf_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function getReferenceCodeAttribute(): string
    {
        return 'MM-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }
}
