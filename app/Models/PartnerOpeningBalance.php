<?php

namespace App\Models;

use App\Enums\PartnerOpeningBalanceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_partner_id', 'effective_date', 'balance_type', 'amount_usd', 'notes', 'created_by'])]
class PartnerOpeningBalance extends Model
{
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'balance_type' => PartnerOpeningBalanceType::class,
            'amount_usd' => 'decimal:2',
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
