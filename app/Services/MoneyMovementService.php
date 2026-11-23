<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\DealStatus;
use App\Enums\MoneyMovementStatus;
use App\Enums\MoneyMovementType;
use App\Enums\PartnerRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\FinancialAccount;
use App\Models\MoneyMovement;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoneyMovementService
{
    public function __construct(private readonly CurrencyConversionService $conversion) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): MoneyMovement
    {
        return DB::transaction(function () use ($data, $userId): MoneyMovement {
            $normalized = $this->normalize($data);

            return MoneyMovement::create([
                ...$normalized,
                'status' => MoneyMovementStatus::Draft,
                'created_by' => $userId,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(MoneyMovement $movement, array $data): MoneyMovement
    {
        return DB::transaction(function () use ($movement, $data): MoneyMovement {
            $locked = MoneyMovement::query()->lockForUpdate()->findOrFail($movement->id);
            $this->ensureDraft($locked);
            $locked->update($this->normalize($data));

            return $locked->refresh();
        });
    }

    public function confirm(MoneyMovement $movement, int $userId): bool
    {
        return DB::transaction(function () use ($movement, $userId): bool {
            $locked = MoneyMovement::query()->lockForUpdate()->findOrFail($movement->id);
            if ($locked->status === MoneyMovementStatus::Confirmed) {
                return false;
            }

            $this->lockDependencies($locked);
            $this->ensureDraft($locked);
            $this->validateDomain($locked->getAttributes());
            $amountUsd = $this->conversion->toUsd($locked->amount, $locked->currency, $locked->exchange_rate_to_usd);
            $locked->update([
                'amount_usd' => $amountUsd,
                'status' => MoneyMovementStatus::Confirmed,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            return true;
        });
    }

    public function delete(MoneyMovement $movement): void
    {
        DB::transaction(function () use ($movement): void {
            $locked = MoneyMovement::query()->lockForUpdate()->findOrFail($movement->id);
            $this->ensureDraft($locked);
            $locked->delete();
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $type = MoneyMovementType::from($data['movement_type']);
        $currency = Currency::from($data['currency']);
        $normalized = [
            ...Arr::except($data, ['money_route', 'confirm_now', 'return_to', 'status', 'confirmed_by', 'confirmed_at', 'amount_usd']),
            'movement_type' => $type,
            'currency' => $currency,
            'exchange_rate_to_usd' => $currency === Currency::Afn ? $data['exchange_rate_to_usd'] : null,
            'amount_usd' => $this->conversion->toUsd($data['amount'], $currency, $data['exchange_rate_to_usd'] ?? null),
        ];
        $this->validateDomain($normalized);

        return $normalized;
    }

    /** @param array<string, mixed> $data */
    private function validateDomain(array $data): void
    {
        $type = $data['movement_type'] instanceof MoneyMovementType ? $data['movement_type'] : MoneyMovementType::from($data['movement_type']);
        $sarafId = $data['saraf_id'] ?? null;
        $accountId = $data['financial_account_id'] ?? null;
        if ((bool) $sarafId === (bool) $accountId) {
            throw ValidationException::withMessages(['money_route' => __('finance.errors.one_money_route')]);
        }

        $counterpartyId = $data['counterparty_id'] ?? null;
        if ($type->isCommercial() && ! $counterpartyId) {
            throw ValidationException::withMessages(['counterparty_id' => __('finance.errors.counterparty_required')]);
        }
        if (! $type->isCommercial() && ($counterpartyId || ($data['deal_id'] ?? null))) {
            throw ValidationException::withMessages(['counterparty_id' => __('finance.errors.other_movement_scope')]);
        }

        $counterparty = $counterpartyId ? BusinessPartner::query()->with('roles')->findOrFail($counterpartyId) : null;
        $requiredRole = match ($type) {
            MoneyMovementType::CustomerReceipt => PartnerRole::Customer,
            MoneyMovementType::SupplierPayment => PartnerRole::Supplier,
            default => null,
        };
        if ($counterparty && (! $counterparty->is_active || ($requiredRole && ! $counterparty->roles->contains('role', $requiredRole)))) {
            throw ValidationException::withMessages(['counterparty_id' => __('finance.errors.counterparty_role')]);
        }

        if ($sarafId) {
            $saraf = BusinessPartner::query()->with('roles')->findOrFail($sarafId);
            if (! $saraf->is_active || ! $saraf->roles->contains('role', PartnerRole::Saraf)) {
                throw ValidationException::withMessages(['saraf_id' => __('finance.errors.saraf_role')]);
            }
            if ((int) $sarafId === (int) $counterpartyId) {
                throw ValidationException::withMessages(['saraf_id' => __('finance.errors.distinct_parties')]);
            }
        }

        if ($accountId) {
            $account = FinancialAccount::query()->findOrFail($accountId);
            $currency = $data['currency'] instanceof Currency ? $data['currency'] : Currency::from($data['currency']);
            if (! $account->is_active || $account->currency !== $currency) {
                throw ValidationException::withMessages(['financial_account_id' => __('finance.errors.account_currency')]);
            }
        }

        if ($dealId = ($data['deal_id'] ?? null)) {
            $deal = Deal::query()->with(['sources', 'destinations'])->findOrFail($dealId);
            if ($deal->status !== DealStatus::Completed) {
                throw ValidationException::withMessages(['deal_id' => __('finance.errors.deal_not_completed')]);
            }
            $belongs = $type === MoneyMovementType::CustomerReceipt
                ? $deal->destinations->contains('customer_id', (int) $counterpartyId)
                : $deal->sources->contains('supplier_id', (int) $counterpartyId);
            if (! $belongs) {
                throw ValidationException::withMessages(['deal_id' => __('finance.errors.deal_counterparty')]);
            }
        }
    }

    private function lockDependencies(MoneyMovement $movement): void
    {
        BusinessPartner::query()->whereIn('id', array_filter([$movement->counterparty_id, $movement->saraf_id]))->lockForUpdate()->get();
        if ($movement->financial_account_id) {
            FinancialAccount::query()->whereKey($movement->financial_account_id)->lockForUpdate()->firstOrFail();
        }
        if ($movement->deal_id) {
            Deal::query()->whereKey($movement->deal_id)->lockForUpdate()->firstOrFail();
        }
    }

    private function ensureDraft(MoneyMovement $movement): void
    {
        if ($movement->status !== MoneyMovementStatus::Draft) {
            throw ValidationException::withMessages(['status' => __('finance.errors.confirmed_immutable')]);
        }
    }
}
