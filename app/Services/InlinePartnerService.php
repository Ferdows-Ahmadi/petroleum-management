<?php

namespace App\Services;

use App\Enums\PartnerRole;
use App\Models\BusinessPartner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InlinePartnerService
{
    public function resolve(?int $partnerId, ?string $name, ?string $phone, PartnerRole $role): BusinessPartner
    {
        return DB::transaction(function () use ($partnerId, $name, $phone, $role): BusinessPartner {
            // A row lock also serializes the lookup when the requested name does
            // not exist yet. Locking a nonexistent party alone cannot do that.
            DB::table('operation_locks')->where('name', 'inline-party')->lockForUpdate()->first();
            if ($partnerId) {
                $partner = BusinessPartner::query()->lockForUpdate()->findOrFail($partnerId);
                $this->ensureActive($partner);
                $this->ensureRole($partner, $role);

                return $partner;
            }

            $normalizedName = preg_replace('/\s+/u', ' ', trim((string) $name));
            if ($normalizedName === '') {
                throw ValidationException::withMessages(['partner_name' => __('office.errors.partner_required')]);
            }

            $partner = BusinessPartner::query()
                ->whereRaw('LOWER(name) = LOWER(?)', [$normalizedName])
                ->lockForUpdate()
                ->first();

            if ($partner) {
                $this->ensureActive($partner);
                if (! $partner->phone && $phone) {
                    $partner->update(['phone' => trim($phone)]);
                }
                $this->ensureRole($partner, $role);

                return $partner;
            }

            $partner = BusinessPartner::create([
                'name' => $normalizedName,
                'phone' => $phone ? trim($phone) : null,
                'is_active' => true,
            ]);
            $partner->roles()->create(['role' => $role]);

            return $partner;
        });
    }

    private function ensureRole(BusinessPartner $partner, PartnerRole $role): void
    {
        $partner->roles()->firstOrCreate(['role' => $role]);
    }

    private function ensureActive(BusinessPartner $partner): void
    {
        if (! $partner->is_active) {
            throw ValidationException::withMessages(['partner_name' => __('office.errors.partner_inactive')]);
        }
    }
}
