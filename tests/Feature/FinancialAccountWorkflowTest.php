<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\FinancialAccountBalanceService;
use App\Services\FinancialTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class FinancialAccountWorkflowTest extends TestCase
{
    use CreatesFinanceFixtures, CreatesTradeFixtures, RefreshDatabase;

    public function test_accountant_can_create_financial_account(): void
    {
        $user = $this->financeUser();
        $response = $this->actingAs($user)->post(route('financial-accounts.store'), [
            'name' => 'نقد دفتر آزمایشی', 'account_type' => 'CASH', 'currency' => 'USD',
            'opening_balance' => '500', 'opening_balance_date' => '2026-08-01', 'description' => 'آزمون', 'is_active' => '1',
        ]);
        $account = FinancialAccount::firstOrFail();
        $response->assertRedirect(route('financial-accounts.show', $account));
        $this->assertSame('500.00', $account->opening_balance);
    }

    public function test_general_manager_can_create_financial_account(): void
    {
        $user = $this->financeUser(UserRole::GeneralManager);
        $this->actingAs($user)->get(route('financial-accounts.create'))->assertOk();
    }

    public function test_accountant_can_update_and_delete_unused_account(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user);
        $this->actingAs($user)->put(route('financial-accounts.update', $account), [
            'name' => 'حساب ویرایش‌شده', 'account_type' => 'BANK', 'currency' => 'USD',
            'opening_balance' => '10', 'opening_balance_date' => '2026-08-01', 'description' => 'ویرایش', 'is_active' => '1',
        ])->assertRedirect(route('financial-accounts.show', $account));
        $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'name' => 'حساب ویرایش‌شده', 'account_type' => 'BANK']);
        $this->actingAs($user)->delete(route('financial-accounts.destroy', $account))->assertRedirect(route('financial-accounts.index'));
        $this->assertDatabaseMissing('financial_accounts', ['id' => $account->id]);
    }

    public function test_opening_partner_balance_request_rejects_zero(): void
    {
        $user = $this->financeUser();
        $partner = $this->tradePartner([PartnerRole::Customer]);
        $this->actingAs($user)->put(route('partners.opening-balance.update', $partner), [
            'effective_date' => '2026-08-01', 'balance_type' => 'RECEIVABLE', 'amount_usd' => '0',
        ])->assertSessionHasErrors('amount_usd');
    }

    public function test_unauthorized_operational_role_cannot_create_account(): void
    {
        $this->actingAs($this->financeUser(UserRole::WarehouseOfficer))->post(route('financial-accounts.store'), [])->assertForbidden();
    }

    public function test_current_balance_is_derived_and_not_a_database_column(): void
    {
        $this->assertFalse(Schema::hasColumn('financial_accounts', 'current_balance'));
        $user = $this->financeUser();
        $account = $this->financeAccount($user, Currency::Usd, '125');
        $this->assertSame('125.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_draft_transaction_does_not_affect_account_balance(): void
    {
        $user = $this->financeUser();
        $partner = $this->tradePartner([PartnerRole::Customer]);
        $account = $this->financeAccount($user, Currency::Usd, '100');
        $this->financeTransaction($this->paymentPayload($account, $partner, '25'), FinancialTransactionType::PaymentReceived, $user);
        $this->assertSame('100.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_confirmed_receipt_increases_account_balance(): void
    {
        [$account, $user, $partner] = $this->context('100');
        $this->financeTransaction($this->paymentPayload($account, $partner, '25'), FinancialTransactionType::PaymentReceived, $user, true);
        $this->assertSame('125.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_confirmed_payment_decreases_account_balance(): void
    {
        [$account, $user, $partner] = $this->context('100');
        $this->financeTransaction($this->paymentPayload($account, $partner, '25'), FinancialTransactionType::PaymentPaid, $user, true);
        $this->assertSame('75.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_confirmed_expense_decreases_account_without_partner_effect(): void
    {
        [$account, $user, $partner] = $this->context('100');
        $payload = $this->paymentPayload($account, $partner, '25');
        unset($payload['business_partner_id']);
        $payload['expense_category'] = ExpenseCategory::Office->value;
        $payload['payee'] = 'فروشگاه آزمایشی';
        $transaction = $this->financeTransaction($payload, FinancialTransactionType::Expense, $user, true);
        $this->assertNull($transaction->business_partner_id);
        $this->assertSame('75.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_account_rejects_mismatched_transaction_currency(): void
    {
        [$account, $user, $partner] = $this->context();
        $payload = $this->paymentPayload($account, $partner);
        $payload['currency'] = 'AFN';
        $payload['exchange_rate_to_usd'] = '70';
        $this->expectException(ValidationException::class);
        $this->financeTransaction($payload, FinancialTransactionType::PaymentReceived, $user);
    }

    public function test_legacy_payment_creation_is_closed_in_favor_of_money_movements(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, Currency::Afn);
        $partner = $this->tradePartner([PartnerRole::Customer]);
        $payload = $this->paymentPayload($account, $partner, '7000');
        $payload['transaction_type'] = 'PAYMENT_RECEIVED';
        $this->actingAs($user)->post(route('payments.store'), $payload)->assertConflict();
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_confirmed_afn_receipt_stores_usd_equivalent_and_native_account_effect(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, Currency::Afn, '1000');
        $partner = $this->tradePartner([PartnerRole::Customer]);
        $transaction = $this->financeTransaction(
            $this->paymentPayload($account, $partner, '7000', '2026-08-08', '70'),
            FinancialTransactionType::PaymentReceived,
            $user,
            true,
        );

        $this->assertSame('100.00', $transaction->amount_usd);
        $this->assertSame('8000.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_confirmed_activity_locks_account_currency_and_opening_baseline(): void
    {
        [$account, $user, $partner] = $this->context('100');
        $this->financeTransaction($this->paymentPayload($account, $partner, '25'), FinancialTransactionType::PaymentReceived, $user, true);

        $this->actingAs($user)->put(route('financial-accounts.update', $account), [
            'name' => $account->name,
            'account_type' => $account->account_type->value,
            'currency' => 'AFN',
            'opening_balance' => '200',
            'opening_balance_date' => '2026-08-02',
            'description' => $account->description,
            'is_active' => '1',
        ])->assertSessionHasErrors('opening_balance');

        $account->refresh();
        $this->assertSame(Currency::Usd, $account->currency);
        $this->assertSame('100.00', $account->opening_balance);
        $this->assertSame('2026-08-01', $account->opening_balance_date->toDateString());
    }

    public function test_confirmation_is_idempotent_and_applies_effect_once(): void
    {
        [$account, $user, $partner] = $this->context('100');
        $transaction = $this->financeTransaction($this->paymentPayload($account, $partner, '25'), FinancialTransactionType::PaymentReceived, $user);
        $service = app(FinancialTransactionService::class);
        $this->assertTrue($service->confirm($transaction, $user->id));
        $this->assertFalse($service->confirm($transaction, $user->id));
        $this->assertSame('125.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_confirmed_transaction_cannot_be_edited_or_deleted(): void
    {
        [$account, $user, $partner] = $this->context();
        $transaction = $this->financeTransaction($this->paymentPayload($account, $partner), FinancialTransactionType::PaymentReceived, $user, true);
        $this->assertSame(FinancialTransactionStatus::Confirmed, $transaction->status);
        $this->actingAs($user)->get(route('payments.edit', $transaction))->assertRedirect(route('payments.show', $transaction));
        $this->expectException(ValidationException::class);
        app(FinancialTransactionService::class)->delete($transaction);
    }

    /** @return array{FinancialAccount, User, BusinessPartner} */
    private function context(string $opening = '0'): array
    {
        $user = $this->financeUser();
        $partner = $this->tradePartner([PartnerRole::Customer, PartnerRole::Supplier]);
        $account = $this->financeAccount($user, Currency::Usd, $opening);

        return [$account, $user, $partner];
    }
}
