<?php

namespace Tests\Feature;

use App\Enums\CapitalAdjustmentDirection;
use App\Enums\CapitalTransactionStatus;
use App\Enums\CapitalTransactionType;
use App\Enums\UserRole;
use App\Models\CapitalPartner;
use App\Models\CapitalTransaction;
use App\Services\CapitalBalanceService;
use App\Services\CapitalTransactionService;
use App\Services\FinancialAccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\TestCase;

class CapitalWorkflowTest extends TestCase
{
    use CreatesFinanceFixtures, RefreshDatabase;

    public function test_complete_capital_submission_is_locked_until_an_authorized_confirmation_posts_once(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user);
        $this->actingAs($user)->post(route('capital-partners.store'), ['name' => 'شریک آزمون', 'is_active' => 1])->assertRedirect();
        $partner = CapitalPartner::firstOrFail();

        $data = $this->payload($partner, $account->id, CapitalTransactionType::InitialCapital, '100', '1405/05/19');
        $data['operation_key'] = (string) Str::uuid();
        $response = $this->post(route('capital-transactions.store'), $data)->assertSessionHasNoErrors();
        $transaction = CapitalTransaction::sole();
        $details = route('capital-transactions.show', $transaction);
        $response->assertRedirect($details);
        $this->post(route('capital-transactions.store'), $data)->assertRedirect($details)->assertSessionHas('operation_recovery');
        $this->assertDatabaseCount('capital_transactions', 1);
        $this->assertSame('2026-08-10', $transaction->transaction_date->toDateString());
        $this->assertSame(CapitalTransactionStatus::PendingApproval, $transaction->status);
        $this->assertSame($user->id, $transaction->created_by);
        $this->assertNull($transaction->confirmed_by);
        $this->assertNull($transaction->confirmed_at);
        $this->assertSame('0.00', app(CapitalBalanceService::class)->balanceFor($partner));
        $this->assertSame('0.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
        $this->get($details)->assertOk()->assertSee('1405/05/19')->assertSee(route('capital-transactions.confirm', $transaction), false)
            ->assertDontSee(route('capital-transactions.edit', $transaction), false);
        $this->put(route('capital-transactions.update', $transaction), array_replace($data, ['amount_usd' => '200']))
            ->assertSessionHasErrors('status');

        $this->actingAs($this->financeUser(UserRole::SystemAdmin))->post(route('capital-transactions.confirm', $transaction))->assertForbidden();
        $this->assertSame(CapitalTransactionStatus::PendingApproval, $transaction->refresh()->status);

        $approver = $this->financeUser(UserRole::GeneralManager);
        $this->actingAs($approver)->post(route('capital-transactions.confirm', $transaction))->assertRedirect();
        $this->post(route('capital-transactions.confirm', $transaction))->assertRedirect();
        $this->assertSame($approver->id, $transaction->refresh()->confirmed_by);
        $this->assertSame(CapitalTransactionStatus::Confirmed, $transaction->status);
        $this->assertSame('100.00', app(CapitalBalanceService::class)->balanceFor($partner));
        $this->assertSame('100.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_withdrawn_capital_request_cannot_be_approved_or_recreated_by_a_retry(): void
    {
        $user = $this->financeUser();
        $cash = $this->financeAccount($user, opening: '125');
        $partner = CapitalPartner::create(['name' => 'Capital approval fixture', 'is_active' => true]);
        $input = $this->payload($partner, $cash->id, CapitalTransactionType::Withdrawal, '25');
        $input['operation_key'] = (string) Str::uuid();
        $this->actingAs($user)->post(route('capital-transactions.store'), $input)->assertSessionHasNoErrors();
        $record = CapitalTransaction::sole();
        $this->delete(route('capital-transactions.destroy', $record))->assertSessionHasNoErrors();
        $this->assertSame(CapitalTransactionStatus::Cancelled, $record->refresh()->status);
        $this->get(route('capital-transactions.show', $record))->assertDontSee(route('capital-transactions.confirm', $record), false);
        $this->post(route('capital-transactions.confirm', $record))->assertSessionHasErrors('status');
        $this->post(route('capital-transactions.store'), $input)->assertSessionHas('operation_recovery');
        $this->delete(route('capital-transactions.destroy', $record))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('capital_transactions', 1);
        $this->assertNull($record->refresh()->confirmed_by);
        $this->assertSame('25.00', $record->amount_usd);
        $this->assertSame('125.00', app(FinancialAccountBalanceService::class)->balanceFor($cash));
        $this->assertSame('0.00', app(CapitalBalanceService::class)->balanceFor($partner));
    }

    public function test_historical_capital_draft_can_still_be_corrected_or_deleted(): void
    {
        $user = $this->financeUser();
        $cash = $this->financeAccount($user);
        $partner = CapitalPartner::create(['name' => 'Historic fixture', 'is_active' => true]);
        $input = $this->payload($partner, $cash->id, CapitalTransactionType::Contribution, '75');
        $record = CapitalTransaction::create($input + ['status' => CapitalTransactionStatus::Draft, 'created_by' => $user->id]);
        $this->actingAs($user)->put(route('capital-transactions.update', $record), array_replace($input, ['amount_usd' => '50']))
            ->assertSessionHasNoErrors();
        $this->assertSame(CapitalTransactionStatus::Draft, $record->refresh()->status);
        $this->assertSame('50.00', $record->amount_usd);
        $this->delete(route('capital-transactions.destroy', $record))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('capital_transactions', ['id' => $record->id]);
    }

    public function test_confirmed_cash_capital_changes_account_and_partner_balances_once(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, opening: '25');
        $partner = CapitalPartner::create(['name' => 'Cash Owner', 'is_active' => true]);
        $transaction = app(CapitalTransactionService::class)->create($this->payload($partner, $account->id, CapitalTransactionType::Contribution, '125'), $user->id);

        $this->assertTrue(app(CapitalTransactionService::class)->confirm($transaction, $user->id));
        $this->assertFalse(app(CapitalTransactionService::class)->confirm($transaction, $user->id));
        $this->assertSame('125.00', app(CapitalBalanceService::class)->balanceFor($partner));
        $this->assertSame('150.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_withdrawal_reduces_both_capital_and_cash(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, opening: '500');
        $partner = CapitalPartner::create(['name' => 'Withdrawal Owner', 'is_active' => true]);
        $this->confirmed($partner, $account->id, CapitalTransactionType::InitialCapital, '200', $user->id);
        $this->confirmed($partner, $account->id, CapitalTransactionType::Withdrawal, '30', $user->id);

        $this->assertSame('170.00', app(CapitalBalanceService::class)->balanceFor($partner));
        $this->assertSame('670.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_non_cash_allocations_change_capital_without_changing_cash(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, opening: '100');
        $partner = CapitalPartner::create(['name' => 'Allocation Owner', 'is_active' => true]);
        $this->confirmed($partner, null, CapitalTransactionType::ProfitAllocation, '40', $user->id);
        $this->confirmed($partner, null, CapitalTransactionType::LossAllocation, '15', $user->id);

        $this->assertSame('25.00', app(CapitalBalanceService::class)->balanceFor($partner));
        $this->assertSame('100.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_adjustment_direction_controls_balance_effect(): void
    {
        $user = $this->financeUser();
        $partner = CapitalPartner::create(['name' => 'Adjustment Owner', 'is_active' => true]);
        $data = $this->payload($partner, null, CapitalTransactionType::Adjustment, '60');
        $data['adjustment_direction'] = CapitalAdjustmentDirection::Increase->value;
        $transaction = app(CapitalTransactionService::class)->create($data, $user->id);
        app(CapitalTransactionService::class)->confirm($transaction, $user->id);

        $this->assertSame('60.00', app(CapitalBalanceService::class)->balanceFor($partner));
    }

    public function test_confirmed_transaction_is_immutable_and_cannot_be_deleted(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user);
        $partner = CapitalPartner::create(['name' => 'Immutable Owner', 'is_active' => true]);
        $transaction = $this->confirmed($partner, $account->id, CapitalTransactionType::InitialCapital, '100', $user->id);

        $this->expectException(ValidationException::class);
        app(CapitalTransactionService::class)->delete($transaction);
    }

    public function test_cash_type_requires_active_usd_account(): void
    {
        $user = $this->financeUser();
        $partner = CapitalPartner::create(['name' => 'Invalid Cash Owner', 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(CapitalTransactionService::class)->create($this->payload($partner, null, CapitalTransactionType::Contribution, '10'), $user->id);
    }

    public function test_non_cash_type_rejects_financial_account(): void
    {
        $user = $this->financeUser();
        $partner = CapitalPartner::create(['name' => 'Invalid Allocation Owner', 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(CapitalTransactionService::class)->create($this->payload($partner, $this->financeAccount($user)->id, CapitalTransactionType::ProfitAllocation, '10'), $user->id);
    }

    public function test_system_admin_can_view_but_cannot_mutate_capital(): void
    {
        $admin = $this->financeUser(UserRole::SystemAdmin);
        $partner = CapitalPartner::create(['name' => 'Read Only Owner', 'is_active' => true]);

        $this->actingAs($admin)->get(route('capital-partners.index'))->assertOk();
        $this->actingAs($admin)->get(route('capital-partners.show', $partner))->assertOk();
        $this->actingAs($admin)->get(route('capital-partners.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('capital-partners.store'), ['name' => 'Forbidden'])->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function payload(CapitalPartner $partner, ?int $accountId, CapitalTransactionType $type, string $amount, string $date = '2026-08-10'): array
    {
        return ['capital_partner_id' => $partner->id, 'financial_account_id' => $accountId, 'transaction_date' => $date, 'transaction_type' => $type->value, 'adjustment_direction' => null, 'amount_usd' => $amount, 'notes' => 'Test'];
    }

    private function confirmed(CapitalPartner $partner, ?int $accountId, CapitalTransactionType $type, string $amount, int $userId): CapitalTransaction
    {
        $transaction = app(CapitalTransactionService::class)->create($this->payload($partner, $accountId, $type, $amount), $userId);
        app(CapitalTransactionService::class)->confirm($transaction, $userId);

        return $transaction->refresh();
    }
}
