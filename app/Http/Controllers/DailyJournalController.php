<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\MoneyMovementType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Models\MoneyMovement;
use App\Services\DailyJournalService;
use App\Services\PartnerLedgerService;
use App\Support\JalaliDate;
use App\Support\RelatedDeals;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyJournalController extends Controller
{
    public function __invoke(Request $request, DailyJournalService $journal, PartnerLedgerService $partnerLedger, JalaliDate $dates, RelatedDeals $deals): View
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
            $movementBalances = [];
            $summary['entries'] = $summary['entries']->map(function (array $entry) use ($partnerLedger, &$movementBalances): array {
                $source = $entry['source'] ?? null;
                $counterparty = $source instanceof MoneyMovement ? $source->counterparty : null;
                $entry['remaining_balance'] = null;

                if ($counterparty) {
                    $movementBalances[$counterparty->id] ??= $partnerLedger->statementFor($counterparty)
                        ->filter(fn (array $item): bool => $item['source'] instanceof MoneyMovement)
                        ->pluck('running_balance', 'source.id');
                    $entry['remaining_balance'] = $movementBalances[$counterparty->id]->get($source->id);
                }

                return $entry;
            });
        }
        $currency = $account?->currency->value ?? 'USD';
        $canRecordFinance = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::Accountant);

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
            'movementTypes' => MoneyMovementType::cases(),
            'expenseCategories' => ExpenseCategory::cases(),
            'canRecordFinance' => $canRecordFinance,
            'dealOptions' => $canRecordFinance ? $deals->initial(null, (int) $request->old('deal_id')) : null,
        ]);
    }
}
