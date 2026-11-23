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

    public function test_journal_ledger_shows_confirmed_rows_for_the_selected_account_and_jalali_day(): void
    {
        [$account, $user, $partner] = $this->context();
        $post = fn ($account, $type, $amount, $day = '2026-08-08', $confirmed = true) => $this->financeTransaction(
            $this->paymentPayload($account, $partner, $amount, $day), $type, $user, $confirmed
        );
        $received = $post($account, FinancialTransactionType::PaymentReceived, '1100.50');
        $paid = $post($account, FinancialTransactionType::PaymentPaid, '25.25');
        $elsewhere = $post($this->financeAccount($user), FinancialTransactionType::PaymentReceived, '500');
        $later = $post($account, FinancialTransactionType::PaymentReceived, '25', '2026-08-09');
        $draft = $post($account, FinancialTransactionType::PaymentReceived, '50', confirmed: false);

        $this->actingAs($user)
            ->get(route('finance.journal', ['context' => 'account', 'account_id' => $account->id, 'date' => '1405/05/17']))
            ->assertOk()->assertSee('1405/05/17')->assertSee('data-ledger-search', false)
            ->assertSeeInOrder([$received->reference_code, '1,100.50', '1,200.50 USD', $paid->reference_code, '25.25', '1,175.25 USD'])
            ->assertSee(route('payments.show', $paid), false)
            ->assertDontSee($elsewhere->reference_code)->assertDontSee($later->reference_code)->assertDontSee($draft->reference_code);
    }

    private function context(): array
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, opening: '100');
        $partner = $this->tradePartner([PartnerRole::Customer, PartnerRole::Supplier]);

        return [$account, $user, $partner];
    }
}
