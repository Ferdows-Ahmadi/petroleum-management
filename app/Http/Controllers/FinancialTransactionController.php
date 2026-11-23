<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Http\Requests\ExpenseRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Services\FinancialTransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialTransactionController extends Controller
{
    public function payments(Request $request): View
    {
        $transactions = $this->query($request)->whereIn('transaction_type', [FinancialTransactionType::PaymentReceived->value, FinancialTransactionType::PaymentPaid->value])->paginate(15)->withQueryString();

        return view('financial-transactions.index', ['transactions' => $transactions, 'mode' => 'payments']);
    }

    public function expenses(Request $request): View
    {
        $transactions = $this->query($request)->where('transaction_type', FinancialTransactionType::Expense->value)->paginate(15)->withQueryString();

        return view('financial-transactions.index', ['transactions' => $transactions, 'mode' => 'expenses']);
    }

    public function createPayment(Request $request): View
    {
        $type = FinancialTransactionType::tryFrom($request->input('type'));
        if (! in_array($type, [FinancialTransactionType::PaymentReceived, FinancialTransactionType::PaymentPaid], true)) {
            $type = FinancialTransactionType::PaymentReceived;
        }

        return $this->form(new FinancialTransaction(['transaction_date' => now(), 'transaction_type' => $type, 'currency' => Currency::Usd]), 'payments');
    }

    public function createExpense(): View
    {
        return $this->form(new FinancialTransaction(['transaction_date' => now(), 'transaction_type' => FinancialTransactionType::Expense, 'currency' => Currency::Usd]), 'expenses');
    }

    public function storePayment(PaymentRequest $request, FinancialTransactionService $service): RedirectResponse
    {
        $transaction = $service->create($request->validated(), FinancialTransactionType::from($request->validated('transaction_type')), $request->user()->id);

        return redirect()->route('payments.show', $transaction)->with('success', __('finance.transaction_created'));
    }

    public function storeExpense(ExpenseRequest $request, FinancialTransactionService $service): RedirectResponse
    {
        $transaction = $service->create($request->validated(), FinancialTransactionType::Expense, $request->user()->id);

        return redirect()->route('expenses.show', $transaction)->with('success', __('finance.transaction_created'));
    }

    public function show(Request $request, FinancialTransaction $financialTransaction): View
    {
        $this->ensureRouteMode($financialTransaction, $request->routeIs('expenses.*') ? 'expenses' : 'payments');
        $financialTransaction->load(['account', 'partner', 'creator', 'confirmer']);

        return view('financial-transactions.show', compact('financialTransaction'));
    }

    public function editPayment(FinancialTransaction $financialTransaction): View|RedirectResponse
    {
        return $this->edit($financialTransaction, 'payments');
    }

    public function editExpense(FinancialTransaction $financialTransaction): View|RedirectResponse
    {
        return $this->edit($financialTransaction, 'expenses');
    }

    public function updatePayment(PaymentRequest $request, FinancialTransaction $financialTransaction, FinancialTransactionService $service): RedirectResponse
    {
        $this->ensureRouteMode($financialTransaction, 'payments');
        $service->update($financialTransaction, $request->validated());

        return redirect()->route('payments.show', $financialTransaction)->with('success', __('finance.transaction_updated'));
    }

    public function updateExpense(ExpenseRequest $request, FinancialTransaction $financialTransaction, FinancialTransactionService $service): RedirectResponse
    {
        $this->ensureRouteMode($financialTransaction, 'expenses');
        $service->update($financialTransaction, $request->validated());

        return redirect()->route('expenses.show', $financialTransaction)->with('success', __('finance.transaction_updated'));
    }

    public function confirm(Request $request, FinancialTransaction $financialTransaction, FinancialTransactionService $service): RedirectResponse
    {
        $this->ensureRouteMode($financialTransaction, $request->routeIs('expenses.*') ? 'expenses' : 'payments');
        $changed = $service->confirm($financialTransaction, $request->user()->id);

        return back()->with('success', $changed ? __('finance.transaction_confirmed') : __('finance.already_confirmed'));
    }

    public function destroy(Request $request, FinancialTransaction $financialTransaction, FinancialTransactionService $service): RedirectResponse
    {
        $mode = $financialTransaction->transaction_type === FinancialTransactionType::Expense ? 'expenses' : 'payments';
        $this->ensureRouteMode($financialTransaction, $request->routeIs('expenses.*') ? 'expenses' : 'payments');
        $service->delete($financialTransaction);

        return redirect()->route($mode.'.index')->with('success', __('finance.transaction_deleted'));
    }

    private function edit(FinancialTransaction $transaction, string $mode): View|RedirectResponse
    {
        $this->ensureRouteMode($transaction, $mode);
        if ($transaction->status !== FinancialTransactionStatus::Draft) {
            return redirect()->route($mode.'.show', $transaction)->with('error', __('finance.errors.confirmed_immutable'));
        }

        return $this->form($transaction, $mode);
    }

    private function form(FinancialTransaction $transaction, string $mode): View
    {
        return view('financial-transactions.form', [
            'financialTransaction' => $transaction,
            'mode' => $mode,
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'partners' => BusinessPartner::query()->where('is_active', true)->orderBy('name')->get(),
            'currencies' => Currency::cases(),
            'expenseCategories' => ExpenseCategory::cases(),
        ]);
    }

    private function query(Request $request): Builder
    {
        return FinancialTransaction::query()->with(['account', 'partner'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->value();
                $query->where(fn (Builder $query) => $query->where('reference', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")->orWhereHas('partner', fn (Builder $partner) => $partner->where('name', 'like', "%{$search}%")));
            })
            ->when(in_array($request->input('status'), array_column(FinancialTransactionStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->latest('transaction_date')->latest('id');
    }

    private function ensureRouteMode(FinancialTransaction $transaction, string $mode): void
    {
        $isExpense = $transaction->transaction_type === FinancialTransactionType::Expense;
        abort_unless(($mode === 'expenses') === $isExpense, 404);
    }
}
