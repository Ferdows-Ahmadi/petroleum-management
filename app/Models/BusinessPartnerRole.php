<?php

namespace App\Models;

use App\Enums\PartnerRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['role'])]
class BusinessPartnerRole extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['role' => PartnerRole::class];
    }

    /** @return BelongsTo<BusinessPartner, $this> */
    public function businessPartner(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class);
    }
}
