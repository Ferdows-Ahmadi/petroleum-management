<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\FinancialAccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'account_type', 'currency', 'opening_balance', 'opening_balance_date', 'description', 'is_active', 'created_by'])]
class FinancialAccount extends Model
{
    protected function casts(): array
    {
        return [
            'account_type' => FinancialAccountType::class,
            'currency' => Currency::class,
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /** @return HasMany<MoneyMovement, $this> */
    public function moneyMovements(): HasMany
    {
        return $this->hasMany(MoneyMovement::class);
    }

    /** @return HasMany<CapitalTransaction, $this> */
    public function capitalTransactions(): HasMany
    {
        return $this->hasMany(CapitalTransaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getReferenceCodeAttribute(): string
    {
        return 'ACC-'.str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }
}
