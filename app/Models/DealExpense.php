<?php

namespace App\Models;

use App\Enums\CommercialRecordStatus;
use App\Enums\Currency;
use App\Enums\DealExpenseCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['deal_id', 'category', 'expense_date', 'amount', 'currency', 'exchange_rate_to_usd', 'amount_usd', 'payee_id', 'financial_transaction_id', 'reference', 'notes', 'status', 'created_by', 'confirmed_by', 'confirmed_at'])]
class DealExpense extends Model
{
    protected function casts(): array
    {
        return [
            'category' => DealExpenseCategory::class,
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'exchange_rate_to_usd' => 'decimal:6',
            'amount_usd' => 'decimal:2',
            'status' => CommercialRecordStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'payee_id');
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->financial_transaction_id !== null;
    }
}
