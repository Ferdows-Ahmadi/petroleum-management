<?php

namespace App\Http\Controllers;

use App\Enums\FinancialTransactionType;
use App\Enums\UserRole;
use App\Http\Requests\ExpenseRequest;
use App\Services\CapitalBalanceService;
use App\Services\DailyJournalService;
use App\Services\FinancialAccountBalanceService;
use App\Services\FinancialTransactionService;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficeFinanceController extends Controller
{
    public function index(
        Request $request,
        DailyJournalService $journal,
        FinancialAccountBalanceService $accountBalances,
        CapitalBalanceService $capitalBalances,
        JalaliDate $dates,
    ): RedirectResponse {
        $tab = in_array($request->input('tab'), ['journal', 'accounts', 'expenses', 'capital'], true) ? $request->input('tab') : 'journal';

        return match ($tab) {
            'accounts' => $request->user()->hasAnyRole(UserRole::Accountant) ? redirect()->route('financial-accounts.index') : redirect()->route('office.configuration', ['tab' => 'financial']),
            'capital' => redirect()->route('capital-partners.index'),
            'expenses' => redirect()->route('finance.journal', ['entry' => 'expense']),
            default => redirect()->route('finance.journal', array_filter(['date' => $request->input('date'), 'account_id' => $request->integer('account_id') ?: null])),
        };
    }

    public function storeExpense(ExpenseRequest $request, FinancialTransactionService $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service): void {
            $transaction = $service->create($request->validated(), FinancialTransactionType::Expense, $request->user()->id);
            $service->confirm($transaction, $request->user()->id);
        });

        return redirect()->route('finance.journal')->with('success', __('office.expense_recorded'));
    }
}
