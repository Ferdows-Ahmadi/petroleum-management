<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\MoneyMovementStatus;
use App\Enums\MoneyMovementType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\MoneyMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\DailyJournalService;
use App\Services\DealMoneySummaryService;
use App\Services\DealService;
use App\Services\FinancialAccountBalanceService;
use App\Services\MoneyMovementService;
use App\Services\PartnerLedgerService;
use App\Services\SarafLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\TestCase;

class MoneyMovementWorkflowTest extends TestCase
{
    use CreatesFinanceFixtures, RefreshDatabase;

    public function test_canonical_deal_receipt_and_payment_update_every_projection_once(): void
    {
        [$deal, $supplier, $customer, $saraf, $user] = $this->canonicalContext();

        $receipt = $this->movement($user, [
            'movement_type' => MoneyMovementType::CustomerReceipt->value,
            'counterparty_id' => $customer->id,
            'saraf_id' => $saraf->id,
            'deal_id' => $deal->id,
            'amount' => '25355.00',
        ], true);
        $payment = $this->movement($user, [
            'movement_type' => MoneyMovementType::SupplierPayment->value,
            'counterparty_id' => $supplier->id,
            'saraf_id' => $saraf->id,
            'deal_id' => $deal->id,
            'amount' => '20000.00',
        ], true);

        $ledger = app(PartnerLedgerService::class);
        $this->assertSame('0.05', $ledger->balanceFor($customer));
        $this->assertSame('-3226.00', $ledger->balanceFor($supplier));
        $this->assertSame('5355.00', app(SarafLedgerService::class)->balanceFor($saraf));
        $this->assertCount(2, app(SarafLedgerService::class)->statementFor($saraf));

        $journal = app(DailyJournalService::class)->forSaraf($saraf, '2026-08-30');
        $this->assertSame('25355.00', $journal['received']);
        $this->assertSame('20000.00', $journal['paid']);
        $this->assertSame('5355.00', $journal['closing_balance']);
        $this->assertCount(2, $journal['entries']);

        $summary = app(DealMoneySummaryService::class)->forDeal($deal);
        $this->assertSame('0.05', $summary['remaining_receivable']);
        $this->assertSame('3226.00', $summary['remaining_payable']);
        $this->assertSame('PARTIAL', $summary['settlement_state']);
        $this->assertCount(2, $summary['movements']);
        $this->assertDatabaseCount('money_movements', 2);
        $this->assertDatabaseHas('money_movements', ['id' => $receipt->id, 'status' => 'CONFIRMED']);
        $this->assertDatabaseHas('money_movements', ['id' => $payment->id, 'status' => 'CONFIRMED']);
    }

    public function test_partial_receipts_reduce_customer_balance_over_several_dates(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '300', 'movement_date' => '2026-08-30'], true);
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '500', 'movement_date' => '2026-08-31'], true);

        $this->assertSame('200.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertCount(3, app(PartnerLedgerService::class)->statementFor($customer));
    }

    public function test_multiple_sarafs_each_receive_only_their_own_effect(): void
    {
        [$deal, , $customer, $sarafC, $user] = $this->simpleContext('1000.00', '1000.00');
        $sarafD = $this->party('Saraf D', [PartnerRole::Saraf]);
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $sarafC->id, 'deal_id' => $deal->id, 'amount' => '400'], true);
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $sarafD->id, 'deal_id' => $deal->id, 'amount' => '600'], true);

        $this->assertSame('0.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertSame('400.00', app(SarafLedgerService::class)->balanceFor($sarafC));
        $this->assertSame('600.00', app(SarafLedgerService::class)->balanceFor($sarafD));
    }

    public function test_direct_afn_receipt_requires_rate_and_updates_matching_account_in_native_currency(): void
    {
        [$deal, , $customer, , $user] = $this->simpleContext('100.00', '100.00');
        $account = $this->financeAccount($user, Currency::Afn);
        $movement = $this->movement($user, [
            'movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id,
            'financial_account_id' => $account->id, 'deal_id' => $deal->id,
            'amount' => '7000', 'currency' => 'AFN', 'exchange_rate_to_usd' => '70',
        ], true);

        $this->assertSame('100.00', $movement->amount_usd);
        $this->assertSame('0.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertSame('7000.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
        $this->assertSame('7000.00', app(DailyJournalService::class)->forAccount($account, '2026-08-30')['received']);
    }

    public function test_overpayment_becomes_customer_credit_without_reallocation(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '1100'], true);

        $this->assertSame('-100.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertSame('-100.00', app(DealMoneySummaryService::class)->forDeal($deal)['remaining_receivable']);
    }

    public function test_money_movement_never_fabricates_trade_or_inventory_rows(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $counts = ['deals' => Deal::count(), 'deal_sources' => $deal->sources()->count(), 'deal_destinations' => $deal->destinations()->count()];
        $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '100'], true);

        $this->assertSame($counts['deals'], Deal::count());
        $this->assertSame($counts['deal_sources'], $deal->sources()->count());
        $this->assertSame($counts['deal_destinations'], $deal->destinations()->count());
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_deal_completion_alone_creates_no_payment(): void
    {
        [$deal] = $this->simpleContext('1000.00', '1000.00');
        $this->assertSame('COMPLETED', $deal->status->value);
        $this->assertDatabaseCount('money_movements', 0);
        $this->assertSame('UNPAID', app(DealMoneySummaryService::class)->forDeal($deal)['settlement_state']);
    }

    public function test_confirmed_movement_cannot_be_updated_or_deleted(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $movement = $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '100'], true);

        try {
            app(MoneyMovementService::class)->update($movement, $this->payload(['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '200']));
            $this->fail('Confirmed movement update should fail.');
        } catch (ValidationException) {
            $this->assertSame('100.00', $movement->refresh()->amount);
        }

        $this->expectException(ValidationException::class);
        app(MoneyMovementService::class)->delete($movement);
    }

    public function test_draft_can_be_edited_deleted_and_does_not_affect_ledgers(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $movement = $this->movement($user, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '100']);
        $this->assertSame('1000.00', app(PartnerLedgerService::class)->balanceFor($customer));
        app(MoneyMovementService::class)->update($movement, $this->payload(['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '200']));
        app(MoneyMovementService::class)->delete($movement);
        $this->assertDatabaseCount('money_movements', 0);
    }

    public function test_party_may_hold_customer_supplier_and_saraf_roles(): void
    {
        $party = $this->party('Multi Role', [PartnerRole::Customer, PartnerRole::Supplier, PartnerRole::Saraf]);
        $this->assertCount(3, $party->roles);
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->get(route('office.accounts', ['partner_id' => $party->id]))->assertOk()->assertSee(__('roles.partner.SARAF'));
    }

    public function test_money_routes_enforce_all_six_roles(): void
    {
        [$deal, , $customer, $saraf, $accountant] = $this->simpleContext('1000.00', '1000.00');
        $movement = $this->movement($accountant, ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id, 'deal_id' => $deal->id, 'amount' => '100']);
        foreach ([UserRole::GeneralManager, UserRole::Accountant] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('money-movements.show', $movement))->assertOk();
            $this->post(route('money-movements.store'), [])->assertSessionHasErrors();
        }
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $this->actingAs($admin)->get(route('money-movements.show', $movement))->assertOk();
        $this->post(route('money-movements.store'), [])->assertForbidden();
        foreach ([UserRole::PurchaseSalesOfficer, UserRole::WarehouseOfficer, UserRole::TransportOfficer] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('money-movements.show', $movement))->assertForbidden();
            $this->post(route('money-movements.store'), [])->assertForbidden();
        }
    }

    public function test_accountant_can_confirm_a_saraf_receipt_from_the_account_ui_route(): void
    {
        [$deal, , $customer, $saraf, $accountant] = $this->simpleContext('1000.00', '1000.00');
        $this->actingAs($accountant)->post(route('money-movements.store'), [
            'movement_date' => '1405/06/08', 'movement_type' => 'CUSTOMER_RECEIPT',
            'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id,
            'deal_id' => $deal->id, 'amount' => '800', 'currency' => 'USD',
            'confirm_now' => '1', 'return_to' => 'accounts',
        ])->assertRedirect(route('office.accounts', ['partner_id' => $customer->id]));

        $this->assertDatabaseHas('money_movements', [
            'counterparty_id' => $customer->id, 'saraf_id' => $saraf->id,
            'amount' => '800.00', 'status' => MoneyMovementStatus::Confirmed->value,
        ]);
        $this->assertSame('200.00', app(PartnerLedgerService::class)->balanceFor($customer));
    }

    public function test_money_route_rejects_both_or_neither_saraf_and_account(): void
    {
        [$deal, , $customer, $saraf, $user] = $this->simpleContext('1000.00', '1000.00');
        $account = $this->financeAccount($user);
        foreach ([
            ['saraf_id' => null, 'financial_account_id' => null],
            ['saraf_id' => $saraf->id, 'financial_account_id' => $account->id],
        ] as $route) {
            try {
                $this->movement($user, [
                    'movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id,
                    'deal_id' => $deal->id, 'amount' => '100', ...$route,
                ]);
                $this->fail('Invalid money route should fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('money_route', $exception->errors());
                $this->assertDatabaseCount('money_movements', 0);
            }
        }
    }

    /** @return array{Deal, BusinessPartner, BusinessPartner, BusinessPartner, User} */
    private function canonicalContext(): array
    {
        return $this->dealContext('19355.000', '1200', '1310');
    }

    /** @return array{Deal, BusinessPartner, BusinessPartner, BusinessPartner, User} */
    private function simpleContext(string $purchase, string $sale): array
    {
        return $this->dealContext('1000.000', $purchase, $sale);
    }

    /** @return array{Deal, BusinessPartner, BusinessPartner, BusinessPartner, User} */
    private function dealContext(string $quantity, string $purchaseRate, string $saleRate): array
    {
        $user = User::factory()->create(['role' => UserRole::Accountant]);
        $supplier = $this->party('Supplier A', [PartnerRole::Supplier]);
        $customer = $this->party('Customer B', [PartnerRole::Customer]);
        $saraf = $this->party('Saraf C', [PartnerRole::Saraf]);
        $deal = app(DealService::class)->create([
            'deal_date' => '2026-08-30', 'product_id' => Product::factory()->create()->id,
            'supplier_id' => $supplier->id, 'source_quantity_kg' => $quantity, 'source_rate_per_ton' => $purchaseRate,
            'customer_id' => $customer->id, 'final_quantity_kg' => $quantity, 'destination_rate_per_ton' => $saleRate,
            'submit_action' => 'complete',
        ], $user->id);

        return [$deal, $supplier, $customer, $saraf, $user];
    }

    private function party(string $name, array $roles): BusinessPartner
    {
        $party = BusinessPartner::factory()->create(['name' => $name]);
        $party->roles()->createMany(array_map(fn (PartnerRole $role) => ['role' => $role], $roles));

        return $party->load('roles');
    }

    private function movement(User $user, array $overrides, bool $confirm = false): MoneyMovement
    {
        $service = app(MoneyMovementService::class);
        $movement = $service->create($this->payload($overrides), $user->id);
        if ($confirm) {
            $service->confirm($movement, $user->id);
        }

        return $movement->refresh();
    }

    private function payload(array $overrides): array
    {
        return [...[
            'movement_date' => '2026-08-30', 'movement_type' => MoneyMovementType::CustomerReceipt->value,
            'counterparty_id' => null, 'saraf_id' => null, 'deal_id' => null, 'financial_account_id' => null,
            'amount' => '100', 'currency' => Currency::Usd->value, 'exchange_rate_to_usd' => null,
            'reference' => 'TEST-MM', 'notes' => 'حرکت پول آزمایشی',
        ], ...$overrides];
    }
}
