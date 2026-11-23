<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\MoneyMovementType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\FinancialAccount;
use App\Models\MoneyMovement;
use App\Services\DailyJournalService;
use App\Services\PartnerLedgerService;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyJournalController extends Controller
{
    public function __invoke(Request $request, DailyJournalService $journal, PartnerLedgerService $partnerLedger, JalaliDate $dates): View
    {
        $dateInput = $request->input('date');
        if ($dateInput) {
            try {
                $request->merge(['date' => $dates->toGregorianDate((string) $dateInput)]);
            } catch (\Throwable) {
                // Validation below returns the localized date error.
            }
        }
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'context' => ['nullable', 'in:account,saraf'],
            'account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'saraf_id' => ['nullable', 'integer', 'exists:business_partners,id'],
        ]);
        $accounts = FinancialAccount::query()->where('is_active', true)->orderBy('name')->get();
        $sarafs = BusinessPartner::query()->where('is_active', true)->withRole(PartnerRole::Saraf)->orderBy('name')->get();
        $context = $validated['context'] ?? ($accounts->isNotEmpty() ? 'account' : 'saraf');
        $account = $context === 'account' ? (isset($validated['account_id']) ? $accounts->firstWhere('id', (int) $validated['account_id']) : $accounts->first()) : null;
        $saraf = $context === 'saraf' ? (isset($validated['saraf_id']) ? $sarafs->firstWhere('id', (int) $validated['saraf_id']) : $sarafs->first()) : null;
        $date = $validated['date'] ?? now()->toDateString();
        $summary = $account ? $journal->forAccount($account, $date) : ($saraf ? $journal->forSaraf($saraf, $date) : null);
        if ($summary) {
            $summary['entries'] = $summary['entries']->map(function (array $entry) use ($partnerLedger): array {
                $source = $entry['source'] ?? null;
                $counterparty = $source instanceof MoneyMovement ? $source->counterparty : null;
                $entry['remaining_balance'] = null;

                if ($counterparty) {
                    $ledgerEntry = $partnerLedger->statementFor($counterparty)
                        ->first(fn (array $item): bool => $item['source'] instanceof MoneyMovement
                            && $item['source']->is($source));
                    $entry['remaining_balance'] = $ledgerEntry['running_balance'] ?? null;
                }

                return $entry;
            });
        }
        $currency = $account?->currency->value ?? 'USD';

        return view('finance.journal', [
            'accounts' => $accounts,
            'sarafs' => $sarafs,
            'context' => $context,
            'account' => $account,
            'saraf' => $saraf,
            'date' => $date,
            'summary' => $summary,
            'currency' => $currency,
            'partners' => BusinessPartner::query()->where('is_active', true)->with('roles')->orderBy('name')->get(),
            'deals' => Deal::query()->with(['sources.supplier', 'destinations.customer'])->latest('deal_date')->limit(100)->get(),
            'movementTypes' => MoneyMovementType::cases(),
            'expenseCategories' => ExpenseCategory::cases(),
            'canRecordFinance' => $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::Accountant),
        ]);
    }
}
