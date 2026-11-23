<?php

namespace App\Models;

use App\Enums\PartnerRole;
use Database\Factories\BusinessPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'phone', 'address', 'notes', 'is_active'])]
class BusinessPartner extends Model
{
    /** @use HasFactory<BusinessPartnerFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<BusinessPartnerRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(BusinessPartnerRole::class);
    }

    /** @param Builder<BusinessPartner> $query */
    public function scopeWithRole(Builder $query, PartnerRole|string $role): void
    {
        $value = $role instanceof PartnerRole ? $role->value : $role;

        $query->whereHas('roles', fn (Builder $roles) => $roles->where('role', $value));
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<DealSource, $this> */
    public function dealSources(): HasMany
    {
        return $this->hasMany(DealSource::class, 'supplier_id');
    }

    /** @return HasMany<DealDestination, $this> */
    public function dealDestinations(): HasMany
    {
        return $this->hasMany(DealDestination::class, 'customer_id');
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /** @return HasMany<MoneyMovement, $this> */
    public function moneyMovements(): HasMany
    {
        return $this->hasMany(MoneyMovement::class, 'counterparty_id');
    }

    /** @return HasMany<MoneyMovement, $this> */
    public function sarafMoneyMovements(): HasMany
    {
        return $this->hasMany(MoneyMovement::class, 'saraf_id');
    }

    /** @return HasOne<PartnerOpeningBalance, $this> */
    public function openingBalance(): HasOne
    {
        return $this->hasOne(PartnerOpeningBalance::class);
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function getReferenceCodeAttribute(): string
    {
        return 'BP-'.str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }
}
