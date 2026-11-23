<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'notes', 'is_active'])]
class CapitalPartner extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<CapitalTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(CapitalTransaction::class);
    }

    public function getReferenceCodeAttribute(): string
    {
        return 'CP-'.str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }
}
