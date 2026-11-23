<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\FinancialTransactionType;
use App\Enums\PartnerRole;
use App\Services\DailyJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class DailyJournalTest extends TestCase
{
    use CreatesFinanceFixtures, CreatesTradeFixtures, RefreshDatabase;

    public function test_day_opening_includes_account_baseline_and_prior_confirmed_activity(): void
    {
        [$account, $user, $partner] = $this->context();
        $this->financeTransaction($this->paymentPayload($account, $partner, '50', '2026-08-07'), FinancialTransactionType::PaymentReceived, $user, true);
        $summary = app(DailyJournalService::class)->forAccount($account, '2026-08-08');
        $this->assertSame('150.00', $summary['opening_balance']);
    }

    public function test_daily_receipts_total_correctly(): void
    {
        [$account, $user, $partner] = $this->context();
        $this->financeTransaction($this->paymentPayload($account, $partner, '20'), FinancialTransactionType::PaymentReceived, $user, true);
        $this->financeTransaction($this->paymentPayload($account, $partner, '30'), FinancialTransactionType::PaymentReceived, $user, true);
        $this->assertSame('50.00', app(DailyJournalService::class)->forAccount($account, '2026-08-08')['received']);
    }

    public function test_daily_payments_total_correctly(): void
    {
        [$account, $user, $partner] = $this->context();
        $this->financeTransaction($this->paymentPayload($account, $partner, '10'), FinancialTransactionType::PaymentPaid, $user, true);
        $this->assertSame('10.00', app(DailyJournalService::class)->forAccount($account, '2026-08-08')['paid']);
    }

    public function test_daily_expenses_total_correctly(): void
    {
        [$account, $user, $partner] = $this->context();
        $payload = $this->paymentPayload($account, $partner, '5');
        unset($payload['business_partner_id']);
        $payload['expense_category'] = ExpenseCategory::Other->value;
        $this->financeTransaction($payload, FinancialTransactionType::Expense, $user, true);
        $this->assertSame('5.00', app(DailyJournalService::class)->forAccount($account, '2026-08-08')['expenses']);
    }

    public function test_closing_balance_uses_opening_plus_inflows_minus_all_outflows(): void
    {
        [$account, $user, $partner] = $this->context();
        $this->financeTransaction($this->paymentPayload($account, $partner, '20'), FinancialTransactionType::PaymentReceived, $user, true);
        $this->financeTransaction($this->paymentPayload($account, $partner, '10'), FinancialTransactionType::PaymentPaid, $user, true);
        $payload = $this->paymentPayload($account, $partner, '5');
        unset($payload['business_partner_id']);
        $payload['expense_category'] = ExpenseCategory::Office->value;
        $this->financeTransaction($payload, FinancialTransactionType::Expense, $user, true);
        $this->assertSame('105.00', app(DailyJournalService::class)->forAccount($account, '2026-08-08')['closing_balance']);
    }

    public function test_draft_transactions_are_excluded_from_journal(): void
    {
        [$account, $user, $partner] = $this->context();
        $this->financeTransaction($this->paymentPayload($account, $partner, '20'), FinancialTransactionType::PaymentReceived, $user);
        $summary = app(DailyJournalService::class)->forAccount($account, '2026-08-08');
        $this->assertSame('0.00', $summary['received']);
        $this->assertSame('100.00', $summary['closing_balance']);
        $this->assertCount(0, $summary['entries']);
    }

    private function context(): array
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, opening: '100');
        $partner = $this->tradePartner([PartnerRole::Customer, PartnerRole::Supplier]);

        return [$account, $user, $partner];
    }
}
