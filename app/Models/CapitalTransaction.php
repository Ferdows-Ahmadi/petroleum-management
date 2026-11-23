<?php

namespace App\Models;

use App\Enums\CapitalAdjustmentDirection;
use App\Enums\CapitalTransactionStatus;
use App\Enums\CapitalTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'capital_partner_id', 'financial_account_id', 'transaction_date', 'transaction_type',
    'adjustment_direction', 'amount_usd', 'notes', 'status', 'created_by', 'confirmed_by', 'confirmed_at',
])]
class CapitalTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'transaction_type' => CapitalTransactionType::class,
            'adjustment_direction' => CapitalAdjustmentDirection::class,
            'amount_usd' => 'decimal:2',
            'status' => CapitalTransactionStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CapitalPartner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(CapitalPartner::class, 'capital_partner_id');
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
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
        return 'CAP-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }
}
