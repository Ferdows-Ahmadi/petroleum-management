<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\FinancialTransactionType;
use App\Enums\PartnerOpeningBalanceType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\PartnerOpeningBalance;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Services\FinancialAccountBalanceService;
use App\Services\PartnerLedgerService;
use App\Services\TradeConfirmationService;
use App\Services\TradeDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class PartnerFinanceTest extends TestCase
{
    use CreatesFinanceFixtures, CreatesTradeFixtures, RefreshDatabase;

    public function test_opening_receivable_creates_positive_balance(): void
    {
        [$partner, $user] = $this->partnerContext();
        $this->opening($partner, $user, PartnerOpeningBalanceType::Receivable, '500');
        $this->assertSame('500.00', app(PartnerLedgerService::class)->balanceFor($partner));
    }

    public function test_opening_payable_creates_negative_balance(): void
    {
        [$partner, $user] = $this->partnerContext();
        $this->opening($partner, $user, PartnerOpeningBalanceType::Payable, '500');
        $this->assertSame('-500.00', app(PartnerLedgerService::class)->balanceFor($partner));
    }

    public function test_confirmed_sale_increases_partner_balance(): void
    {
        [$sale, $user, $customer] = $this->confirmedExactSale();
        $this->assertSame('25355.05', $sale->total_amount);
        $this->assertSame('25355.05', app(PartnerLedgerService::class)->balanceFor($customer));
    }

    public function test_confirmed_purchase_decreases_partner_balance(): void
    {
        [$purchase, $supplier] = $this->confirmedPurchase('10000', '1000');
        $this->assertSame('10000.00', $purchase->total_amount);
        $this->assertSame('-10000.00', app(PartnerLedgerService::class)->balanceFor($supplier));
    }

    public function test_payment_received_decreases_partner_balance(): void
    {
        [$partner, $user] = $this->partnerContext();
        $this->opening($partner, $user, PartnerOpeningBalanceType::Receivable, '1000');
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $partner, '400'), FinancialTransactionType::PaymentReceived, $user, true);
        $this->assertSame('600.00', app(PartnerLedgerService::class)->balanceFor($partner));
    }

    public function test_payment_paid_increases_partner_balance(): void
    {
        [$partner, $user] = $this->partnerContext();
        $this->opening($partner, $user, PartnerOpeningBalanceType::Payable, '1000');
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $partner, '400'), FinancialTransactionType::PaymentPaid, $user, true);
        $this->assertSame('-600.00', app(PartnerLedgerService::class)->balanceFor($partner));
    }

    public function test_both_role_partner_supports_mixed_history(): void
    {
        [$partner, $user] = $this->partnerContext();
        $product = $this->tradeProduct('BOTH');
        $warehouse = $this->tradeWarehouse();
        $purchase = app(TradeDraftService::class)->createPurchase($this->purchasePayload($partner, $product, $warehouse, '1000', '1000'), $user->id);
        app(TradeConfirmationService::class)->confirmPurchase($purchase, $user->id);
        $sale = app(TradeDraftService::class)->createSale($this->salePayload($partner, $product, $warehouse, '500', '1000'), $user->id);
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user, '1500', '1000');
        app(TradeConfirmationService::class)->confirmSale($sale, $user->id);
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $partner, '100', '2026-08-09'), FinancialTransactionType::PaymentReceived, $user, true);
        $this->financeTransaction($this->paymentPayload($account, $partner, '100', '2026-08-10'), FinancialTransactionType::PaymentPaid, $user, true);
        $this->assertSame('-500.00', app(PartnerLedgerService::class)->balanceFor($partner));
        $this->assertSame(['SALE', 'PURCHASE', 'PAYMENT_RECEIVED', 'PAYMENT_PAID'], app(PartnerLedgerService::class)->statementFor($partner)->pluck('type')->all());
        $this->assertCount(2, $partner->roles);
    }

    public function test_draft_payment_has_no_partner_ledger_effect(): void
    {
        [$partner, $user] = $this->partnerContext();
        $this->opening($partner, $user, PartnerOpeningBalanceType::Receivable, '100');
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $partner, '100'), FinancialTransactionType::PaymentReceived, $user);
        $this->assertSame('100.00', app(PartnerLedgerService::class)->balanceFor($partner));
        $this->assertCount(1, app(PartnerLedgerService::class)->statementFor($partner));
    }

    public function test_running_statement_is_chronological_and_exact(): void
    {
        [$partner, $user] = $this->partnerContext();
        $this->opening($partner, $user, PartnerOpeningBalanceType::Receivable, '100', '2026-08-01');
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $partner, '20', '2026-08-03'), FinancialTransactionType::PaymentPaid, $user, true);
        $this->financeTransaction($this->paymentPayload($account, $partner, '50', '2026-08-02'), FinancialTransactionType::PaymentReceived, $user, true);
        $statement = app(PartnerLedgerService::class)->statementFor($partner);
        $this->assertSame(['2026-08-01', '2026-08-02', '2026-08-03'], $statement->pluck('date')->all());
        $this->assertSame(['100.00', '50.00', '70.00'], $statement->pluck('running_balance')->all());
    }

    public function test_positive_negative_and_zero_positions_are_classified_correctly(): void
    {
        $user = $this->financeUser();
        $receivable = $this->tradePartner([PartnerRole::Customer]);
        $payable = $this->tradePartner([PartnerRole::Supplier]);
        $settled = $this->tradePartner([PartnerRole::Customer]);
        $this->opening($receivable, $user, PartnerOpeningBalanceType::Receivable, '100');
        $this->opening($payable, $user, PartnerOpeningBalanceType::Payable, '80');
        $this->opening($settled, $user, PartnerOpeningBalanceType::Receivable, '50');
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $settled, '50'), FinancialTransactionType::PaymentReceived, $user, true);
        $ledger = app(PartnerLedgerService::class);
        $this->assertTrue($ledger->positions('receivable')->pluck('partner.id')->contains($receivable->id));
        $this->assertTrue($ledger->positions('payable')->pluck('partner.id')->contains($payable->id));
        $this->assertFalse($ledger->positions('receivable')->pluck('partner.id')->contains($settled->id));
        $this->assertFalse($ledger->positions('payable')->pluck('partner.id')->contains($settled->id));
        $this->assertSame('80.00', $ledger->positions('payable')->firstWhere('partner.id', $payable->id)['amount']);
    }

    public function test_inactive_partners_with_balances_remain_in_positions_and_dashboard_totals(): void
    {
        $user = $this->financeUser();
        $receivable = $this->tradePartner([PartnerRole::Customer]);
        $payable = $this->tradePartner([PartnerRole::Supplier]);
        $this->opening($receivable, $user, PartnerOpeningBalanceType::Receivable, '125');
        $this->opening($payable, $user, PartnerOpeningBalanceType::Payable, '80');
        $receivable->update(['is_active' => false]);
        $payable->update(['is_active' => false]);

        $ledger = app(PartnerLedgerService::class);
        $this->assertSame('125.00', $ledger->positions('receivable')->firstWhere('partner.id', $receivable->id)['amount']);
        $this->assertSame('80.00', $ledger->positions('payable')->firstWhere('partner.id', $payable->id)['amount']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['receivables'] === '125.00' && $metrics['payables'] === '80.00');
    }

    public function test_inactive_partner_is_marked_and_last_activity_is_jalali(): void
    {
        [$partner, $user] = $this->partnerContext();
        $this->opening($partner, $user, PartnerOpeningBalanceType::Receivable, '100', '2026-08-01');
        $partner->update(['is_active' => false]);

        $this->actingAs($user)->get(route('finance.receivables'))
            ->assertOk()
            ->assertSee(__('common.inactive'))
            ->assertSee('1405/05/10')
            ->assertDontSee('2026-08-01');
    }

    public function test_inactive_historical_partner_cannot_be_used_for_new_trade_or_payment(): void
    {
        $partner = $this->tradePartner([PartnerRole::Customer, PartnerRole::Supplier]);
        $user = $this->financeUser(UserRole::GeneralManager);
        $this->opening($partner, $user, PartnerOpeningBalanceType::Receivable, '100');
        $partner->update(['is_active' => false]);

        $this->actingAs($user)->post(route('purchases.store'), $this->purchasePayload(
            $partner,
            $this->tradeProduct(),
            $this->tradeWarehouse(),
        ))->assertSessionHasErrors('business_partner_id');

        $this->actingAs($user)->post(route('money-movements.store'), [
            'movement_type' => 'CUSTOMER_RECEIPT',
            'counterparty_id' => $partner->id,
            'financial_account_id' => $this->financeAccount($user)->id,
            'movement_date' => '1405/05/10',
            'amount' => '10',
            'currency' => 'USD',
        ])->assertSessionHasErrors('counterparty_id');
    }

    public function test_opening_balance_is_editable_before_confirmed_activity(): void
    {
        [$partner, $user] = $this->partnerContext();

        $payload = ['effective_date' => '1405/05/10', 'balance_type' => PartnerOpeningBalanceType::Receivable->value, 'amount_usd' => '100', 'notes' => null];
        $this->actingAs($user)->put(route('partners.opening-balance.update', $partner), $payload)->assertRedirect(route('partners.statement', $partner));
        $payload['amount_usd'] = '175';
        $this->actingAs($user)->put(route('partners.opening-balance.update', $partner), $payload)->assertRedirect(route('partners.statement', $partner));

        $this->assertSame('175.00', $partner->openingBalance()->firstOrFail()->amount_usd);
    }

    public function test_opening_balance_is_locked_after_confirmed_sale(): void
    {
        [$sale, $user, $partner] = $this->confirmedExactSale();

        $this->actingAs($user)->put(route('partners.opening-balance.update', $partner), $this->openingPayload())
            ->assertSessionHasErrors('opening_balance');
        $this->assertFalse($partner->openingBalance()->exists());
        $this->assertSame('25355.05', $sale->total_amount);
    }

    public function test_opening_balance_is_locked_after_confirmed_purchase(): void
    {
        [$purchase, $partner, $user] = $this->confirmedPurchase('1000', '1000');

        $this->actingAs($user)->put(route('partners.opening-balance.update', $partner), $this->openingPayload())
            ->assertSessionHasErrors('opening_balance');
        $this->assertFalse($partner->openingBalance()->exists());
        $this->assertSame('1000.00', $purchase->total_amount);
    }

    public function test_opening_balance_is_locked_after_confirmed_payment(): void
    {
        [$partner, $user] = $this->partnerContext();
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $partner, '25'), FinancialTransactionType::PaymentReceived, $user, true);

        $this->actingAs($user)->put(route('partners.opening-balance.update', $partner), $this->openingPayload())
            ->assertSessionHasErrors('opening_balance');
        $this->assertFalse($partner->openingBalance()->exists());
    }

    public function test_opening_balance_deletion_is_blocked_after_confirmed_activity(): void
    {
        [$partner, $user] = $this->partnerContext();
        $opening = $this->opening($partner, $user, PartnerOpeningBalanceType::Receivable, '100');
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $partner, '25'), FinancialTransactionType::PaymentReceived, $user, true);

        $this->actingAs($user)->delete(route('partners.opening-balance.destroy', $partner))
            ->assertSessionHasErrors('opening_balance');
        $this->assertDatabaseHas('partner_opening_balances', ['id' => $opening->id]);
    }

    public function test_flagship_sale_and_receipt_leave_five_cents_and_increase_cash_exactly(): void
    {
        [$sale, $user, $customer] = $this->confirmedExactSale();
        $account = $this->financeAccount($user);
        $this->assertSame('0.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
        $this->financeTransaction($this->paymentPayload($account, $customer, '25355.00'), FinancialTransactionType::PaymentReceived, $user, true);
        $this->assertSame('25355.05', $sale->total_amount);
        $this->assertSame('0.05', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertSame('25355.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_supplier_purchase_and_partial_payment_leave_six_thousand_payable(): void
    {
        [$purchase, $supplier, $user] = $this->confirmedPurchase('10000', '1000');
        $account = $this->financeAccount($user, Currency::Usd, '10000');
        $this->financeTransaction($this->paymentPayload($account, $supplier, '4000'), FinancialTransactionType::PaymentPaid, $user, true);
        $this->assertSame('10000.00', $purchase->total_amount);
        $this->assertSame('-6000.00', app(PartnerLedgerService::class)->balanceFor($supplier));
        $this->assertSame('6000.00', app(PartnerLedgerService::class)->positions('payable')->firstWhere('partner.id', $supplier->id)['amount']);
    }

    public function test_expense_does_not_change_partner_balance(): void
    {
        [$partner, $user] = $this->partnerContext();
        $this->opening($partner, $user, PartnerOpeningBalanceType::Receivable, '100');
        $account = $this->financeAccount($user, Currency::Usd, '500');
        $payload = $this->paymentPayload($account, $partner, '25');
        unset($payload['business_partner_id']);
        $payload['expense_category'] = ExpenseCategory::Office->value;
        $this->financeTransaction($payload, FinancialTransactionType::Expense, $user, true);
        $this->assertSame('100.00', app(PartnerLedgerService::class)->balanceFor($partner));
    }

    /** @return array{BusinessPartner, User} */
    private function partnerContext(): array
    {
        return [$this->tradePartner([PartnerRole::Customer, PartnerRole::Supplier]), $this->financeUser()];
    }

    private function opening($partner, $user, PartnerOpeningBalanceType $type, string $amount, string $date = '2026-08-01'): PartnerOpeningBalance
    {
        return PartnerOpeningBalance::create(['business_partner_id' => $partner->id, 'effective_date' => $date, 'balance_type' => $type, 'amount_usd' => $amount, 'notes' => 'آزمون', 'created_by' => $user->id]);
    }

    /** @return array<string, mixed> */
    private function openingPayload(): array
    {
        return ['effective_date' => '1405/05/10', 'balance_type' => PartnerOpeningBalanceType::Receivable->value, 'amount_usd' => '100', 'notes' => null];
    }

    /** @return array{Sale, User, BusinessPartner} */
    private function confirmedExactSale(): array
    {
        $user = $this->financeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('FIN-SALE');
        $warehouse = $this->tradeWarehouse();
        $this->addStock($user, $supplier, $product, $warehouse, '30000');
        $sale = app(TradeDraftService::class)->createSale($this->salePayload($customer, $product, $warehouse, '20000', '1310'), $user->id);
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user, '34475', '15120');
        app(TradeConfirmationService::class)->confirmSale($sale, $user->id);

        return [$sale->refresh()->load('items'), $user, $customer];
    }

    /** @return array{Purchase, BusinessPartner, User} */
    private function confirmedPurchase(string $quantity, string $rate): array
    {
        $user = $this->financeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct('FIN-PUR');
        $warehouse = $this->tradeWarehouse();
        $purchase = app(TradeDraftService::class)->createPurchase($this->purchasePayload($supplier, $product, $warehouse, $quantity, $rate), $user->id);
        app(TradeConfirmationService::class)->confirmPurchase($purchase, $user->id);

        return [$purchase->refresh()->load('items'), $supplier, $user];
    }
}
