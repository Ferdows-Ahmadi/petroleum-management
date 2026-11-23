<?php

namespace App\Http\Controllers;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Services\InlinePartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InlinePartyController extends Controller
{
    public function store(Request $request, InlinePartnerService $partners): JsonResponse
    {
        $data = $request->validate([
            'partner_id' => ['nullable', 'integer', Rule::exists('business_partners', 'id')->whereNull('deleted_at')],
            'name' => ['nullable', 'required_without:partner_id', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in([PartnerRole::Customer->value, PartnerRole::Supplier->value, PartnerRole::Saraf->value])],
        ]);
        $role = PartnerRole::from($data['role']);
        $allowed = $role === PartnerRole::Saraf
            ? $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::Accountant)
            : $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::Accountant, UserRole::PurchaseSalesOfficer);
        abort_unless($allowed, 403);

        $party = DB::transaction(fn () => $partners->resolve(
            isset($data['partner_id']) ? (int) $data['partner_id'] : null,
            $data['name'] ?? null,
            $data['phone'] ?? null,
            $role,
        ));
        $party->load('roles');

        return response()->json([
            'id' => $party->id,
            'name' => $party->name,
            'phone' => $party->phone,
            'reference' => $party->reference_code,
            'roles' => $party->roles->map(fn ($partyRole): string => $partyRole->role->value)->values(),
        ]);
    }
}
