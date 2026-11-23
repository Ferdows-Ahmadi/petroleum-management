<?php

namespace App\Http\Controllers;

use App\Enums\CapitalTransactionStatus;
use App\Enums\Currency;
use App\Enums\FinancialAccountType;
use App\Enums\FinancialTransactionStatus;
use App\Enums\MoneyMovementStatus;
use App\Http\Requests\FinancialAccountRequest;
use App\Models\FinancialAccount;
use App\Services\FinancialAccountBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function index(FinancialAccountBalanceService $balances): View
    {
        $accounts = FinancialAccount::query()->orderBy('name')->get();
        $accountBalances = $accounts->mapWithKeys(fn (FinancialAccount $account): array => [$account->id => $balances->balanceFor($account)]);

        return view('financial-accounts.index', compact('accounts', 'accountBalances'));
    }

    public function create(): View
    {
        return view('financial-accounts.form', [
            'financialAccount' => new FinancialAccount(['opening_balance' => '0', 'opening_balance_date' => now(), 'currency' => Currency::Usd, 'is_active' => true]),
            'accountTypes' => FinancialAccountType::cases(),
            'currencies' => Currency::cases(),
        ]);
    }

    public function store(FinancialAccountRequest $request): RedirectResponse
    {
        $account = FinancialAccount::create([...$request->validated(), 'created_by' => $request->user()->id]);

        return redirect()->route('financial-accounts.show', $account)->with('success', __('finance.account_created'));
    }

    public function show(FinancialAccount $financialAccount, FinancialAccountBalanceService $balances): View
    {
        $financialAccount->load([
            'creator',
            'transactions' => fn ($query) => $query->with('partner')->latest('transaction_date')->latest('id')->limit(20),
            'moneyMovements' => fn ($query) => $query->with(['counterparty', 'deal'])->latest('movement_date')->latest('id')->limit(20),
            'capitalTransactions' => fn ($query) => $query->with('partner')->latest('transaction_date')->latest('id')->limit(20),
        ]);

        return view('financial-accounts.show', ['financialAccount' => $financialAccount, 'currentBalance' => $balances->balanceFor($financialAccount)]);
    }

    public function edit(FinancialAccount $financialAccount): View
    {
        return view('financial-accounts.form', ['financialAccount' => $financialAccount, 'accountTypes' => FinancialAccountType::cases(), 'currencies' => Currency::cases()]);
    }

    public function update(FinancialAccountRequest $request, FinancialAccount $financialAccount): RedirectResponse
    {
        $data = $request->validated();
        $hasConfirmedActivity = $financialAccount->transactions()->where('status', FinancialTransactionStatus::Confirmed->value)->exists()
            || $financialAccount->capitalTransactions()->where('status', CapitalTransactionStatus::Confirmed->value)->exists()
            || $financialAccount->moneyMovements()->where('status', MoneyMovementStatus::Confirmed->value)->exists();
        if ($hasConfirmedActivity) {
            $currentCurrency = $financialAccount->currency->value;
            $currentOpeningDate = $financialAccount->opening_balance_date->toDateString();
            if ($currentCurrency !== $data['currency'] || bccomp($financialAccount->opening_balance, (string) $data['opening_balance'], 2) !== 0 || $currentOpeningDate !== $data['opening_balance_date']) {
                throw ValidationException::withMessages(['opening_balance' => __('finance.errors.account_baseline_locked')]);
            }
        }
        $financialAccount->update($data);

        return redirect()->route('financial-accounts.show', $financialAccount)->with('success', __('finance.account_updated'));
    }

    public function destroy(FinancialAccount $financialAccount): RedirectResponse
    {
        if ($financialAccount->transactions()->exists() || $financialAccount->capitalTransactions()->exists() || $financialAccount->moneyMovements()->exists()) {
            return back()->with('error', __('finance.errors.account_in_use'));
        }
        $financialAccount->delete();

        return redirect()->route('financial-accounts.index')->with('success', __('finance.account_deleted'));
    }
}
