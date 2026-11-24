<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\FinancialTransactionType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class FinanceAuthorizationTest extends TestCase
{
    use CreatesFinanceFixtures, CreatesTradeFixtures, RefreshDatabase;

    public function test_unauthenticated_finance_access_is_blocked(): void
    {
        $this->get(route('financial-accounts.index'))->assertRedirect(route('login'));
        $this->get(route('finance.receivables'))->assertRedirect(route('login'));
    }

    public function test_accountant_has_full_intended_finance_access(): void
    {
        $user = $this->financeUser();
        $this->actingAs($user)->get(route('financial-accounts.index'))->assertOk();
        $this->get(route('payments.index'))->assertOk();
        $this->get(route('expenses.index'))->assertOk();
        $this->get(route('finance.journal'))->assertOk();
        $this->get(route('partners.statements'))->assertOk();
    }

    public function test_system_administrator_can_view_but_not_modify_core_finance(): void
    {
        $user = $this->financeUser(UserRole::SystemAdmin);
        $this->actingAs($user)->get(route('financial-accounts.index'))->assertOk();
        $this->get(route('financial-accounts.create'))->assertForbidden();
        $this->post(route('payments.store'), [])->assertForbidden();
    }

    public function test_purchase_sales_officer_can_view_partner_status_but_not_cash_journal(): void
    {
        $user = $this->financeUser(UserRole::PurchaseSalesOfficer);
        $this->actingAs($user)->get(route('finance.receivables'))->assertOk();
        $this->get(route('partners.statements'))->assertOk();
        $this->get(route('financial-accounts.index'))->assertForbidden();
        $this->get(route('finance.journal'))->assertForbidden();
    }

    public function test_warehouse_and_transport_roles_cannot_view_or_modify_finance(): void
    {
        foreach ([UserRole::WarehouseOfficer, UserRole::TransportOfficer] as $role) {
            $this->actingAs($this->financeUser($role))->get(route('payments.index'))->assertForbidden();
            $this->get(route('finance.receivables'))->assertForbidden();
            $this->post(route('expenses.store'), [])->assertForbidden();
        }
    }

    public function test_payment_received_and_paid_require_partner(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user, Currency::Usd);
        foreach ([FinancialTransactionType::PaymentReceived, FinancialTransactionType::PaymentPaid] as $type) {
            $this->actingAs($user)->post(route('money-movements.store'), [
                'confirm_now' => 1,
                'movement_type' => $type === FinancialTransactionType::PaymentReceived ? 'CUSTOMER_RECEIPT' : 'SUPPLIER_PAYMENT',
                'financial_account_id' => $account->id,
                'movement_date' => '1405/05/17',
                'amount' => '100',
                'currency' => 'USD',
            ])->assertSessionHasErrors('counterparty_id');
        }
    }

    public function test_confirmed_financial_record_is_historical_in_ui(): void
    {
        $user = $this->financeUser();
        $partner = $this->tradePartner([PartnerRole::Customer]);
        $account = $this->financeAccount($user);
        $transaction = $this->financeTransaction($this->paymentPayload($account, $partner), FinancialTransactionType::PaymentReceived, $user, true);
        $this->actingAs($user)->get(route('payments.show', $transaction))->assertOk()->assertSee(__('finance.confirmed_notice'));
        $this->get(route('payments.edit', $transaction))->assertRedirect(route('payments.show', $transaction));
    }
}
