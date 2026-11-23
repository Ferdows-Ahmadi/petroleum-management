<?php

namespace App\Http\Controllers;

use App\Enums\PartnerOpeningBalanceType;
use App\Http\Requests\PartnerOpeningBalanceRequest;
use App\Models\BusinessPartner;
use App\Models\PartnerOpeningBalance;
use App\Services\PartnerLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PartnerFinanceController extends Controller
{
    public function index(Request $request, PartnerLedgerService $ledger): View
    {
        $partners = BusinessPartner::query()->where('is_active', true)->with('roles')->orderBy('name')->get();
        $selectedPartner = $request->integer('partner_id') ? $partners->firstWhere('id', $request->integer('partner_id')) : $partners->first();
        $statement = $selectedPartner ? $ledger->statementFor($selectedPartner) : collect();
        $openingBalanceLocked = $selectedPartner ? $ledger->hasConfirmedActivity($selectedPartner) : false;

        return view('partner-finance.statement', compact('partners', 'selectedPartner', 'statement', 'openingBalanceLocked'));
    }

    public function show(BusinessPartner $businessPartner, PartnerLedgerService $ledger): View
    {
        $businessPartner->load(['roles', 'openingBalance']);

        return view('partner-finance.statement', [
            'partners' => BusinessPartner::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedPartner' => $businessPartner,
            'statement' => $ledger->statementFor($businessPartner),
            'openingBalanceLocked' => $ledger->hasConfirmedActivity($businessPartner),
        ]);
    }

    public function receivables(PartnerLedgerService $ledger): View
    {
        return view('partner-finance.positions', ['positions' => $ledger->positions('receivable'), 'kind' => 'receivable']);
    }

    public function payables(PartnerLedgerService $ledger): View
    {
        return view('partner-finance.positions', ['positions' => $ledger->positions('payable'), 'kind' => 'payable']);
    }

    public function openingForm(BusinessPartner $businessPartner, PartnerLedgerService $ledger): View|RedirectResponse
    {
        if ($ledger->hasConfirmedActivity($businessPartner)) {
            return redirect()->route('partners.statement', $businessPartner)->with('error', __('finance.errors.opening_locked'));
        }

        return view('partner-finance.opening-form', [
            'partner' => $businessPartner,
            'openingBalance' => $businessPartner->openingBalance ?? new PartnerOpeningBalance(['effective_date' => now(), 'balance_type' => PartnerOpeningBalanceType::Receivable]),
            'types' => PartnerOpeningBalanceType::cases(),
        ]);
    }

    public function saveOpening(PartnerOpeningBalanceRequest $request, BusinessPartner $businessPartner, PartnerLedgerService $ledger): RedirectResponse
    {
        $this->ensureOpeningBalanceIsMutable($businessPartner, $ledger);

        $businessPartner->openingBalance()->updateOrCreate(
            ['business_partner_id' => $businessPartner->id],
            [...$request->validated(), 'created_by' => $request->user()->id],
        );

        return redirect()->route('partners.statement', $businessPartner)->with('success', __('finance.opening_saved'));
    }

    public function deleteOpening(BusinessPartner $businessPartner, PartnerLedgerService $ledger): RedirectResponse
    {
        $this->ensureOpeningBalanceIsMutable($businessPartner, $ledger);
        $businessPartner->openingBalance()->delete();

        return redirect()->route('partners.statement', $businessPartner)->with('success', __('finance.opening_deleted'));
    }

    private function ensureOpeningBalanceIsMutable(BusinessPartner $partner, PartnerLedgerService $ledger): void
    {
        if ($ledger->hasConfirmedActivity($partner)) {
            throw ValidationException::withMessages(['opening_balance' => __('finance.errors.opening_locked')]);
        }
    }
}
