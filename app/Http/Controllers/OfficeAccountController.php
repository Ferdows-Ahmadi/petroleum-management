<?php

namespace App\Http\Controllers;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Services\PartnerDirectorySummary;
use App\Services\PartnerLedgerService;
use App\Services\SarafLedgerService;
use App\Support\RelatedDeals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfficeAccountController extends Controller
{
    public function index(
        Request $request,
        PartnerLedgerService $ledger,
        SarafLedgerService $sarafLedger,
        RelatedDeals $deals,
        PartnerDirectorySummary $directory,
    ): View {
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
        $summaries = ! $selected && $canViewMoneyDetails ? $directory->forPartnerIds($partners->modelKeys()) : [];
        $partnerRows = $selected ? collect() : $partners->map(fn (BusinessPartner $partner): array => [
            'partner' => $partner,
            'balance' => $summaries[$partner->id]['balance'] ?? '0.00',
            'last_activity' => $summaries[$partner->id]['last_activity'] ?? null,
        ]);

        return view('office.accounts', [
            'partners' => $partners,
            'partnerRows' => $partnerRows,
            'roleFilter' => $roleFilter,
            'dealOptions' => $selected && $canViewMoneyDetails ? $deals->initial($selected->id, (int) $request->old('deal_id')) : null,
            'selectedPartner' => $selected,
            'statement' => $selectedStatement,
            'balance' => $selectedStatement->last()['running_balance'] ?? '0.00',
            'accounts' => $selected ? FinancialAccount::query()->where('is_active', true)->orderBy('name')->get() : collect(),
            'sarafs' => $selected ? BusinessPartner::query()->where('is_active', true)->withRole(PartnerRole::Saraf)->orderBy('name')->get() : collect(),
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
