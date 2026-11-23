<?php

namespace App\Http\Controllers;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Services\PartnerLedgerService;
use App\Services\SarafLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfficeAccountController extends Controller
{
    public function index(Request $request, PartnerLedgerService $ledger, SarafLedgerService $sarafLedger): View
    {
        $canViewMoneyDetails = $request->user()->hasAnyRole(UserRole::GeneralManager->value, UserRole::Accountant->value);
        $roleFilter = in_array($request->input('role'), array_column(PartnerRole::cases(), 'value'), true) ? $request->input('role') : null;
        $partners = BusinessPartner::query()->with('roles')
            ->when(! $canViewMoneyDetails, fn ($query) => $query->whereDoesntHave('roles', fn ($roles) => $roles->where('role', PartnerRole::Saraf->value)))
            ->when($roleFilter, fn (Builder $query) => $query->withRole($roleFilter))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->value();
                $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id', preg_replace('/\D+/', '', $search)));
            })
            ->orderBy('name')->get();
        $selected = $request->integer('partner_id') ? $partners->firstWhere('id', $request->integer('partner_id')) : null;
        $selectedStatement = $selected && $canViewMoneyDetails ? $ledger->statementFor($selected) : collect();
        $partnerRows = $selected ? collect() : $partners->map(function (BusinessPartner $partner) use ($canViewMoneyDetails, $ledger): array {
            $statement = $canViewMoneyDetails ? $ledger->statementFor($partner) : collect();

            return ['partner' => $partner, 'balance' => $statement->last()['running_balance'] ?? '0.00', 'last_activity' => $statement->last()['date'] ?? null];
        });

        return view('office.accounts', [
            'partners' => $partners,
            'partnerRows' => $partnerRows,
            'roleFilter' => $roleFilter,
            'selectedPartner' => $selected,
            'statement' => $selectedStatement,
            'balance' => $selectedStatement->last()['running_balance'] ?? '0.00',
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'sarafs' => BusinessPartner::query()->where('is_active', true)->withRole(PartnerRole::Saraf)->orderBy('name')->get(),
            'sarafStatement' => $selected && $selected->roles->contains('role', PartnerRole::Saraf) ? $sarafLedger->statementFor($selected) : collect(),
            'sarafBalance' => $selected && $selected->roles->contains('role', PartnerRole::Saraf) ? $sarafLedger->balanceFor($selected) : '0.00',
            'canRecordMoney' => $request->user()->hasAnyRole(UserRole::GeneralManager->value, UserRole::Accountant->value),
            'canViewMoneyDetails' => $canViewMoneyDetails,
            'canEditPartner' => $request->user()->hasAnyRole(
                UserRole::GeneralManager->value,
                UserRole::SystemAdmin->value,
                UserRole::PurchaseSalesOfficer->value,
            ),
        ]);
    }
}
