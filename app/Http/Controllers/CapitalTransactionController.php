<?php

namespace App\Http\Controllers;

use App\Enums\CapitalAdjustmentDirection;
use App\Enums\CapitalTransactionStatus;
use App\Enums\CapitalTransactionType;
use App\Http\Requests\CapitalTransactionRequest;
use App\Models\CapitalPartner;
use App\Models\CapitalTransaction;
use App\Models\FinancialAccount;
use App\Services\CapitalTransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CapitalTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = CapitalTransaction::query()->with(['partner', 'account'])
            ->when($request->filled('capital_partner_id'), fn (Builder $query) => $query->where('capital_partner_id', $request->integer('capital_partner_id')))
            ->when(in_array($request->input('status'), array_column(CapitalTransactionStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->latest('transaction_date')->latest('id')->paginate(15)->withQueryString();

        return view('capital.transactions.index', [
            'transactions' => $transactions,
            'partners' => CapitalPartner::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => CapitalTransactionStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new CapitalTransaction([
            'capital_partner_id' => $request->integer('capital_partner_id') ?: null,
            'transaction_date' => now(),
            'transaction_type' => CapitalTransactionType::Contribution,
        ]));
    }

    public function store(CapitalTransactionRequest $request, CapitalTransactionService $service): RedirectResponse
    {
        $transaction = $service->create($request->validated(), $request->user()->id);

        return redirect()->route('capital-transactions.show', $transaction)->with('success', __('capital.transaction_created'));
    }

    public function show(CapitalTransaction $capitalTransaction): View
    {
        $capitalTransaction->load(['partner', 'account', 'creator', 'confirmer']);

        return view('capital.transactions.show', compact('capitalTransaction'));
    }

    public function edit(CapitalTransaction $capitalTransaction): View|RedirectResponse
    {
        if ($capitalTransaction->status !== CapitalTransactionStatus::Draft) {
            return redirect()->route('capital-transactions.show', $capitalTransaction)->with('error', __('capital.errors.confirmed_immutable'));
        }

        return $this->form($capitalTransaction);
    }

    public function update(CapitalTransactionRequest $request, CapitalTransaction $capitalTransaction, CapitalTransactionService $service): RedirectResponse
    {
        $service->update($capitalTransaction, $request->validated());

        return redirect()->route('capital-transactions.show', $capitalTransaction)->with('success', __('capital.transaction_updated'));
    }

    public function confirm(Request $request, CapitalTransaction $capitalTransaction, CapitalTransactionService $service): RedirectResponse
    {
        $changed = $service->confirm($capitalTransaction, $request->user()->id);

        return back()->with('success', $changed ? __('capital.transaction_confirmed') : __('capital.already_confirmed'));
    }

    public function destroy(CapitalTransaction $capitalTransaction, CapitalTransactionService $service): RedirectResponse
    {
        $service->delete($capitalTransaction);

        return redirect()->route('capital-transactions.index')->with('success', __('capital.transaction_deleted'));
    }

    private function form(CapitalTransaction $capitalTransaction): View
    {
        return view('capital.transactions.form', [
            'capitalTransaction' => $capitalTransaction,
            'partners' => CapitalPartner::query()->where('is_active', true)->orderBy('name')->get(),
            'accounts' => FinancialAccount::query()->where('is_active', true)->where('currency', 'USD')->orderBy('name')->get(),
            'types' => CapitalTransactionType::cases(),
            'adjustmentDirections' => CapitalAdjustmentDirection::cases(),
        ]);
    }
}
