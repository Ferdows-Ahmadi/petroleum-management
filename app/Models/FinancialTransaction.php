<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'financial_account_id', 'business_partner_id', 'transaction_date', 'transaction_type',
    'amount', 'currency', 'exchange_rate_to_usd', 'amount_usd', 'expense_category',
    'payee', 'reference', 'description', 'status', 'created_by', 'confirmed_by', 'confirmed_at',
])]
class FinancialTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'transaction_type' => FinancialTransactionType::class,
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'exchange_rate_to_usd' => 'decimal:6',
            'amount_usd' => 'decimal:2',
            'expense_category' => ExpenseCategory::class,
            'status' => FinancialTransactionStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /** @return BelongsTo<BusinessPartner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'business_partner_id');
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

    /** @return HasOne<DealExpense, $this> */
    public function dealExpense(): HasOne
    {
        return $this->hasOne(DealExpense::class);
    }

    public function getReferenceCodeAttribute(): string
    {
        $prefix = match ($this->transaction_type) {
            FinancialTransactionType::PaymentReceived => 'RC',
            FinancialTransactionType::PaymentPaid => 'PM',
            FinancialTransactionType::Expense => 'EX',
        };

        return $prefix.'-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }
}
