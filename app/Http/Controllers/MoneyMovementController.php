<?php

namespace App\Http\Controllers;

use App\Enums\MoneyMovementStatus;
use App\Enums\MoneyMovementType;
use App\Enums\PartnerRole;
use App\Http\Requests\MoneyMovementRequest;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Models\MoneyMovement;
use App\Services\MoneyMovementService;
use App\Support\JournalContext;
use App\Support\RelatedDeals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MoneyMovementController extends Controller
{
    public function legacyCreate(Request $request): RedirectResponse
    {
        return redirect()->route('office.accounts', array_filter(['partner_id' => $request->integer('partner_id')]))
            ->with('error', __('finance.errors.legacy_payment_entry'));
    }

    public function legacyStore(): never
    {
        abort(409, __('finance.errors.legacy_payment_entry'));
    }

    public function store(MoneyMovementRequest $request, MoneyMovementService $service): RedirectResponse
    {
        $movement = DB::transaction(function () use ($request, $service): MoneyMovement {
            $movement = $service->create($request->validated(), $request->user()->id);
            $service->confirm($movement, $request->user()->id);

            return $movement;
        });

        request()->attributes->set('operation_record_location', route('money-movements.show', $movement, false));

        return $this->redirectFor($request, $movement)->with('success', __('finance.money_confirmed'));
    }

    public function show(MoneyMovement $moneyMovement): View
    {
        $moneyMovement->load(['counterparty.roles', 'saraf.roles', 'deal' => fn ($deal) => $deal->with(RelatedDeals::RELATIONS), 'account', 'creator', 'confirmer']);

        return view('money-movements.show', compact('moneyMovement'));
    }

    public function edit(Request $request, MoneyMovement $moneyMovement, RelatedDeals $deals): View|RedirectResponse
    {
        if ($moneyMovement->status === MoneyMovementStatus::Confirmed) {
            return redirect()->route('money-movements.show', $moneyMovement)->with('error', __('finance.confirmed_notice'));
        }

        return view('money-movements.form', [
            'moneyMovement' => $moneyMovement,
            'partners' => BusinessPartner::query()->where('is_active', true)->with('roles')->orderBy('name')->get(),
            'sarafs' => BusinessPartner::query()->where('is_active', true)->withRole(PartnerRole::Saraf)->orderBy('name')->get(),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'dealOptions' => $deals->initial(null, (int) $request->old('deal_id', $moneyMovement->deal_id)),
            'movementTypes' => MoneyMovementType::cases(),
        ]);
    }

    public function update(MoneyMovementRequest $request, MoneyMovement $moneyMovement, MoneyMovementService $service): RedirectResponse
    {
        $service->update($moneyMovement, $request->validated());

        return redirect()->route('money-movements.show', $moneyMovement)->with('success', __('finance.money_draft_saved'));
    }

    public function confirm(Request $request, MoneyMovement $moneyMovement, MoneyMovementService $service): RedirectResponse
    {
        $changed = $service->confirm($moneyMovement, $request->user()->id);

        return redirect()->route('money-movements.show', $moneyMovement)->with('success', $changed ? __('finance.money_confirmed') : __('finance.already_confirmed'));
    }

    public function destroy(MoneyMovement $moneyMovement, MoneyMovementService $service): RedirectResponse
    {
        $partnerId = $moneyMovement->counterparty_id ?: $moneyMovement->saraf_id;
        $service->delete($moneyMovement);

        return redirect()->route('office.accounts', ['partner_id' => $partnerId])->with('success', __('finance.money_draft_deleted'));
    }

    private function redirectFor(MoneyMovementRequest $request, MoneyMovement $movement): RedirectResponse
    {
        return match ($request->input('return_to')) {
            'deal' => $movement->deal_id ? redirect()->route('deals.show', $movement->deal_id) : redirect()->route('money-movements.show', $movement),
            'journal' => redirect()->route('finance.journal', JournalContext::forPayment($movement->financial_account_id, $movement->saraf_id)),
            default => redirect()->route('office.accounts', ['partner_id' => $movement->counterparty_id ?: $movement->saraf_id]),
        };
    }
}
