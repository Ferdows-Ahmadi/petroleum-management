<?php

namespace App\Http\Controllers;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Http\Requests\ReportFilterRequest;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Services\DailyJournalService;
use App\Services\PartnerDirectorySummary;
use App\Services\PartnerLedgerService;
use Illuminate\View\View;

class AccountReportController extends Controller
{
    public function partners(ReportFilterRequest $request, PartnerDirectorySummary $directory, PartnerLedgerService $ledger): View
    {
        $showBalances = $request->user()->hasAnyRole(UserRole::GeneralManager, UserRole::Accountant);
        $partners = BusinessPartner::query()->with('roles')
            ->when(! $showBalances, fn ($query) => $query->whereDoesntHave('roles', fn ($roles) => $roles->where('role', PartnerRole::Saraf)))
            ->orderBy('name')->orderBy('id')->get();
        $selected = $request->filled('partner_id') ? $partners->findOrFail($request->integer('partner_id')) : null;
        $dateInputs = $request->jalaliDateInputs();
        if ($selected && $showBalances) {
            $from = $request->validated('date_from');
            $to = $request->validated('date_to');
            $statement = $ledger->statementFor($selected);
            $opening = $from ? ($statement->last(fn ($row) => $row['date'] < $from)['running_balance'] ?? '0.00') : '0.00';
            $rows = $statement->filter(fn ($row) => (! $from || $row['date'] >= $from) && (! $to || $row['date'] <= $to));
            $closing = $rows->last()['running_balance'] ?? $opening;

            return view('reports.statement', compact('partners', 'selected', 'dateInputs', 'opening', 'rows', 'closing'));
        }

        $rows = $selected ? collect([$selected]) : $partners;
        $positions = $showBalances ? $directory->forPartnerIds($partners->modelKeys()) : [];
        $receivables = $payables = '0.00';
        foreach ($positions as $position) {
            if (bccomp($position['balance'], '0', 2) > 0) {
                $receivables = bcadd($receivables, $position['balance'], 2);
            } else {
                $payables = bcsub($payables, $position['balance'], 2);
            }
        }

        return view('reports.accounts', compact('partners', 'selected', 'rows', 'positions', 'receivables', 'payables', 'showBalances'));
    }

    public function journal(ReportFilterRequest $request, DailyJournalService $journals): View
    {
        $date = $request->validated('day') ?? now()->toDateString();
        $accounts = FinancialAccount::query()->orderBy('name')->orderBy('id')->get();
        $sarafs = BusinessPartner::query()->withRole(PartnerRole::Saraf)->orderBy('name')->orderBy('id')->get();
        // One selector includes the route so identically numbered accounts and sarafs cannot be confused.
        $book = $request->validated('book');
        $account = $saraf = null;
        if ($book) {
            [$kind, $id] = explode(':', $book);
            if ($kind === 'account') {
                $account = $accounts->findOrFail((int) $id);
            } else {
                $saraf = $sarafs->findOrFail((int) $id);
            }
        } else {
            $account = $accounts->firstWhere('is_active', true) ?? $accounts->first();
            $saraf = $account ? null : ($sarafs->firstWhere('is_active', true) ?? $sarafs->first());
        }
        $summary = $account ? $journals->forAccount($account, $date) : ($saraf ? $journals->forSaraf($saraf, $date) : null);
        $currency = $account?->currency->value ?? 'USD';

        return view('reports.journal', compact('date', 'accounts', 'sarafs', 'account', 'saraf', 'summary', 'currency'));
    }
}
