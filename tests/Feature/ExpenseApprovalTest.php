<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\UserRole;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Services\DailyJournalService;
use App\Services\FinancialAccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\TestCase;

class ExpenseApprovalTest extends TestCase
{
    use CreatesFinanceFixtures, RefreshDatabase;

    public function test_complete_legacy_expense_waits_for_authorized_approval_without_becoming_an_editable_draft(): void
    {
        $accountant = $this->financeUser();
        $cash = $this->financeAccount($accountant, Currency::Afn, '10000');
        $input = $this->expense($cash);

        $response = $this->actingAs($accountant)->post(route('expenses.store'), $input)->assertSessionHasNoErrors();
        $expense = FinancialTransaction::sole();
        $details = route('expenses.show', $expense);
        $response->assertRedirect($details);
        $this->post(route('expenses.store'), $input)->assertRedirect($details)->assertSessionHas('operation_recovery');
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertSame(FinancialTransactionStatus::PendingApproval, $expense->status);
        $this->assertNull($expense->confirmed_by);
        $this->assertNull($expense->confirmed_at);
        $this->assertSame('10000.00', app(FinancialAccountBalanceService::class)->balanceFor($cash));
        $this->assertSame('0.00', app(DailyJournalService::class)->forAccount($cash, '2026-08-10')['expenses']);
        $this->get($details)->assertOk()->assertSee(route('expenses.confirm', $expense), false)
            ->assertDontSee(route('expenses.edit', $expense), false);
        $this->put(route('expenses.update', $expense), array_replace($input, ['amount' => '9000']))->assertSessionHasErrors('status');

        $this->actingAs($this->financeUser(UserRole::SystemAdmin))->post(route('expenses.confirm', $expense))->assertForbidden();
        $this->assertSame(FinancialTransactionStatus::PendingApproval, $expense->refresh()->status);
        $manager = $this->financeUser(UserRole::GeneralManager);
        $this->actingAs($manager)->post(route('expenses.confirm', $expense))->assertRedirect();
        $this->post(route('expenses.confirm', $expense))->assertRedirect();
        $this->assertSame(FinancialTransactionStatus::Confirmed, $expense->refresh()->status);
        $this->assertSame($accountant->id, $expense->created_by);
        $this->assertSame($manager->id, $expense->confirmed_by);
        $this->assertNotNull($expense->confirmed_at);
        $this->assertSame('100.00', $expense->amount_usd);
        $this->assertSame('3000.00', app(FinancialAccountBalanceService::class)->balanceFor($cash));
        $this->assertSame('7000.00', app(DailyJournalService::class)->forAccount($cash, '2026-08-10')['expenses']);
    }

    public function test_journal_expense_requires_the_confirm_button_and_the_same_submission_posts_only_once(): void
    {
        $manager = $this->financeUser(UserRole::GeneralManager);
        $cash = $this->financeAccount($manager, Currency::Afn, '10000');
        $input = $this->expense($cash);
        $route = route('office.finance.expenses.store');

        $this->actingAs($manager)->post($route, $input)->assertSessionHasErrors('confirm_now');
        $this->post($route, $input + ['confirm_now' => '0'])->assertSessionHasErrors('confirm_now');
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('operation_receipts', 0);
        $this->assertSame('10000.00', app(FinancialAccountBalanceService::class)->balanceFor($cash));
        $this->get(route('finance.journal', ['entry' => 'expense']))->assertOk()
            ->assertSee('type="submit" name="confirm_now" value="1"', false)
            ->assertDontSee('type="hidden" name="confirm_now"', false);

        $approved = $input + ['confirm_now' => '1'];
        $this->post($route, $approved)->assertSessionHasNoErrors()->assertRedirect(route('finance.journal'));
        $expense = FinancialTransaction::sole();
        $this->post($route, $approved)->assertRedirect(route('expenses.show', $expense))->assertSessionHas('operation_recovery');
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertSame(FinancialTransactionStatus::Confirmed, $expense->status);
        $this->assertSame($manager->id, $expense->confirmed_by);
        $this->assertSame('3000.00', app(FinancialAccountBalanceService::class)->balanceFor($cash));
        $this->assertSame('7000.00', app(DailyJournalService::class)->forAccount($cash, '2026-08-10')['expenses']);
    }

    public function test_withdrawing_submitted_expense_retains_the_record_and_blocks_late_approval(): void
    {
        $user = $this->financeUser();
        $cash = $this->financeAccount($user, Currency::Afn, '10000');
        $input = $this->expense($cash);
        $this->actingAs($user)->post(route('expenses.store'), $input)->assertSessionHasNoErrors();
        $expense = FinancialTransaction::sole();
        $this->delete(route('expenses.destroy', $expense))->assertSessionHasNoErrors();

        $this->assertSame(FinancialTransactionStatus::Cancelled, $expense->refresh()->status);
        $this->get(route('expenses.show', $expense))->assertDontSee(route('expenses.confirm', $expense), false);
        $this->post(route('expenses.confirm', $expense))->assertSessionHasErrors('status');
        $this->post(route('expenses.store'), $input)->assertSessionHas('operation_recovery');
        $this->delete(route('expenses.destroy', $expense))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertNull($expense->refresh()->confirmed_at);
        $this->assertSame('7000.00', $expense->amount);
        $this->assertSame('10000.00', app(FinancialAccountBalanceService::class)->balanceFor($cash));
        $this->assertSame('0.00', app(DailyJournalService::class)->forAccount($cash, '2026-08-10')['expenses']);
        $this->get(route('reports.expenses', ['status' => 'CANCELLED']))->assertSee($expense->reference_code)
            ->assertViewHas('confirmedTotal', '0.00');
    }

    public function test_existing_expense_draft_can_still_be_edited_and_deleted(): void
    {
        $user = $this->financeUser();
        $cash = $this->financeAccount($user, Currency::Afn, '10000');
        $input = $this->expense($cash);
        $draft = $this->financeTransaction(array_replace($input, ['transaction_date' => '2026-08-10']),
            FinancialTransactionType::Expense, $user);
        $this->actingAs($user)->put(route('expenses.update', $draft), array_replace($input, ['amount' => '1400']))
            ->assertSessionHasNoErrors();
        $this->assertSame(FinancialTransactionStatus::Draft, $draft->refresh()->status);
        $this->assertSame('1400.00', $draft->amount);
        $this->delete(route('expenses.destroy', $draft))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('financial_transactions', ['id' => $draft->id]);
        $this->assertSame('10000.00', app(FinancialAccountBalanceService::class)->balanceFor($cash));
    }

    private function expense(FinancialAccount $cash): array
    {
        return [
            'financial_account_id' => $cash->id, 'transaction_date' => '1405/05/19',
            'expense_category' => 'OFFICE', 'amount' => '7000', 'currency' => 'AFN',
            'exchange_rate_to_usd' => '70', 'reference' => 'QA-EXP-27', 'operation_key' => (string) Str::uuid(),
        ];
    }
}
