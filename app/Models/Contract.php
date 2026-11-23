<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_partner_id', 'contract_number', 'contract_type', 'start_date', 'end_date',
    'agreed_amount', 'currency', 'terms', 'status', 'created_by',
])]
class Contract extends Model
{
    protected function casts(): array
    {
        return [
            'contract_type' => ContractType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'agreed_amount' => 'decimal:2',
            'currency' => Currency::class,
            'status' => ContractStatus::class,
        ];
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
}
