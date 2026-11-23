<?php

namespace Tests\Feature;

use App\Enums\CommercialRecordStatus;
use App\Enums\Currency;
use App\Enums\DealDestinationType;
use App\Enums\DealQuantityAdjustmentType;
use App\Enums\DealSourceType;
use App\Enums\InventoryMovementType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DealAllocationService;
use App\Services\DealCommercialRecordService;
use App\Services\DealService;
use App\Services\DealSettlementService;
use App\Services\InventoryBalanceService;
use App\Services\PartnerLedgerService;
use App\Services\StorageClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DealSettlementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_simple_deal_auto_allocates_and_confirms_without_inventory_or_money_changes(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = $this->directDeal($user, $product, $supplier, $customer, '19355', '1200', '19355', '1310', true);

        $this->assertDatabaseHas('deal_allocations', [
            'deal_id' => $deal->id, 'quantity_kg' => '19355.000', 'allocated_cost_usd' => '23226.00', 'allocation_method' => 'AUTOMATIC',
        ]);
        $settlement = app(DealSettlementService::class)->confirm($deal, $user->id);

        $this->assertSame(CommercialRecordStatus::Confirmed, $settlement->status);
        $this->assertSame('23226.00', $settlement->source_value_usd);
        $this->assertSame('25355.05', $settlement->sale_revenue_usd);
        $this->assertSame('2129.05', $settlement->final_result_usd);
        $this->assertSame('0.000', $settlement->unexplained_quantity_kg);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('money_movements', 0);
        $this->assertSame('-23226.00', app(PartnerLedgerService::class)->balanceFor($supplier));
        $this->assertSame('25355.05', app(PartnerLedgerService::class)->balanceFor($customer));
    }

    public function test_storage_and_shortage_reconcile_quantity_and_create_one_costed_inflow(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $warehouse = Warehouse::factory()->create();
        $deal = $this->directDeal($user, $product, $supplier, $customer, '30000', '1200', '28500', '1310');
        app(DealService::class)->addDestination($deal, [
            'destination_type' => DealDestinationType::Storage->value,
            'warehouse_id' => $warehouse->id,
            'final_quantity_kg' => '1450',
        ]);
        $adjustment = app(DealCommercialRecordService::class)->createAdjustment($deal, [
            'deal_source_id' => $deal->sources()->firstOrFail()->id,
            'adjustment_type' => DealQuantityAdjustmentType::Shortage->value,
            'quantity_kg' => '50',
            'reason' => 'کمی وزن تأییدشده',
        ], $user->id);
        app(DealCommercialRecordService::class)->confirmAdjustment($adjustment, $user->id);
        app(DealService::class)->complete($deal, $user->id);

        $settlement = app(DealSettlementService::class)->confirm($deal, $user->id);
        app(DealSettlementService::class)->prepare($deal, $user->id);

        $this->assertSame('1450.000', $settlement->storage_in_quantity_kg);
        $this->assertSame('50.000', $settlement->shortage_quantity_kg);
        $this->assertSame('60.00', $settlement->shortage_cost_usd);
        $this->assertSame('0.000', $settlement->unexplained_quantity_kg);
        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'movement_type' => InventoryMovementType::DealStorageIn->value,
            'quantity_kg' => '1450.000', 'cost_amount_usd' => '1740.00',
        ]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('1450.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_multi_customer_allocations_create_independent_obligations_and_exact_result(): void
    {
        [$user, $product, $supplier, $customerB] = $this->context();
        $customerC = $this->party(PartnerRole::Customer, 'Customer C');
        $customerD = $this->party(PartnerRole::Customer, 'Customer D');
        $deal = $this->directDeal($user, $product, $supplier, $customerB, '30000', '1200', '10000', '1300');
        app(DealService::class)->addDestination($deal, ['destination_type' => 'CUSTOMER', 'customer_id' => $customerC->id, 'final_quantity_kg' => '12000', 'rate_per_ton' => '1310']);
        app(DealService::class)->addDestination($deal, ['destination_type' => 'CUSTOMER', 'customer_id' => $customerD->id, 'final_quantity_kg' => '8000', 'rate_per_ton' => '1320']);
        app(DealService::class)->complete($deal, $user->id);
        $settlement = app(DealSettlementService::class)->confirm($deal, $user->id);

        $this->assertSame(3, $deal->allocations()->count());
        $this->assertSame('13000.00', app(PartnerLedgerService::class)->balanceFor($customerB));
        $this->assertSame('15720.00', app(PartnerLedgerService::class)->balanceFor($customerC));
        $this->assertSame('10560.00', app(PartnerLedgerService::class)->balanceFor($customerD));
        $this->assertSame('39280.00', $settlement->sale_revenue_usd);
        $this->assertSame('3280.00', $settlement->final_result_usd);
    }

    public function test_multi_source_costs_remain_distinct_and_supplier_obligations_do_not_merge(): void
    {
        [$user, $product, $supplierA, $customer] = $this->context();
        $supplierD = $this->party(PartnerRole::Supplier, 'Supplier D');
        $deal = $this->directDeal($user, $product, $supplierA, $customer, '10000', '1180', '20000', '1310');
        app(DealService::class)->addSource($deal, ['source_type' => 'SUPPLIER', 'supplier_id' => $supplierD->id, 'quantity_kg' => '10000', 'rate_per_ton' => '1220']);
        app(DealService::class)->complete($deal, $user->id);
        $settlement = app(DealSettlementService::class)->confirm($deal, $user->id);

        $this->assertEqualsCanonicalizing(['11800.00', '12200.00'], $deal->allocations()->pluck('allocated_cost_usd')->all());
        $this->assertSame('-11800.00', app(PartnerLedgerService::class)->balanceFor($supplierA));
        $this->assertSame('-12200.00', app(PartnerLedgerService::class)->balanceFor($supplierD));
        $this->assertSame('24000.00', $settlement->source_value_usd);
        $this->assertSame('2200.00', $settlement->final_result_usd);
    }

    public function test_storage_can_supply_a_later_deal_at_weighted_cost_without_becoming_a_supplier_party(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $warehouse = Warehouse::factory()->create();
        $inDeal = $this->directDeal($user, $product, $supplier, $customer, '2000', '1200', '1000', '1300');
        app(DealService::class)->addDestination($inDeal, ['destination_type' => 'STORAGE', 'warehouse_id' => $warehouse->id, 'final_quantity_kg' => '1000']);
        app(DealService::class)->complete($inDeal, $user->id);
        app(DealSettlementService::class)->confirm($inDeal, $user->id);

        $outDeal = app(DealService::class)->create(['deal_date' => '2026-08-31', 'product_id' => $product->id, 'submit_action' => 'draft'], $user->id);
        app(DealService::class)->addSource($outDeal, ['source_type' => DealSourceType::Storage->value, 'warehouse_id' => $warehouse->id, 'quantity_kg' => '1000']);
        app(DealService::class)->addDestination($outDeal, ['destination_type' => 'CUSTOMER', 'customer_id' => $customer->id, 'final_quantity_kg' => '1000', 'rate_per_ton' => '1400']);
        app(DealService::class)->complete($outDeal, $user->id);
        $settlement = app(DealSettlementService::class)->confirm($outDeal, $user->id);

        $source = $outDeal->sources()->firstOrFail();
        $this->assertSame(DealSourceType::Storage, $source->source_type);
        $this->assertNull($source->supplier_id);
        $this->assertSame('1200.0000', $source->rate_per_ton);
        $this->assertSame('1200.00', $source->amount);
        $this->assertSame('200.00', $settlement->final_result_usd);
        $this->assertSame('0.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
        $this->assertDatabaseCount('inventory_movements', 2);

        $revision = app(DealSettlementService::class)->prepare($outDeal, $user->id);
        $reconfirmed = app(DealSettlementService::class)->confirm($outDeal, $user->id);
        $this->assertSame(2, $revision->revision);
        $this->assertSame(2, $reconfirmed->revision);
        $this->assertSame('1200.00', $source->fresh()->amount);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_confirmed_settlement_is_immutable_and_recalculation_creates_a_new_revision(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = $this->directDeal($user, $product, $supplier, $customer, '1000', '1200', '1000', '1310', true);
        $service = app(DealSettlementService::class);
        $first = $service->confirm($deal, $user->id);
        try {
            app(DealCommercialRecordService::class)->createExpense($deal, [
                'category' => 'FREIGHT', 'expense_date' => '2026-08-31', 'amount' => '10', 'currency' => 'USD',
            ], $user->id);
            $this->fail('Confirmed settlement accepted a direct mutation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('deal_expenses', 0);
        }
        $second = $service->prepare($deal, $user->id);
        $correction = app(DealCommercialRecordService::class)->createExpense($deal, [
            'category' => 'FREIGHT', 'expense_date' => '2026-08-31', 'amount' => '10', 'currency' => 'USD',
        ], $user->id);

        $this->assertSame(1, $first->revision);
        $this->assertSame(CommercialRecordStatus::Confirmed, $first->status);
        $this->assertSame(2, $second->revision);
        $this->assertSame(CommercialRecordStatus::Draft, $second->status);
        $this->assertSame(CommercialRecordStatus::Draft, $correction->status);
        $this->assertSame('110.00', $first->fresh()->final_result_usd);
    }

    public function test_settlement_rejects_unexplained_quantity_and_draft_adjustments(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = $this->directDeal($user, $product, $supplier, $customer, '2000', '1200', '1500', '1310');
        app(DealService::class)->addDestination($deal, ['destination_type' => 'STORAGE', 'warehouse_id' => Warehouse::factory()->create()->id, 'final_quantity_kg' => '400']);
        app(DealCommercialRecordService::class)->createAdjustment($deal, [
            'deal_source_id' => $deal->sources()->firstOrFail()->id, 'adjustment_type' => 'SHORTAGE', 'quantity_kg' => '100', 'reason' => 'draft',
        ], $user->id);
        app(DealService::class)->complete($deal, $user->id);

        $this->expectException(ValidationException::class);
        app(DealSettlementService::class)->confirm($deal, $user->id);
    }

    public function test_many_to_many_requires_explicit_allocation_and_rejects_over_allocation(): void
    {
        [$user, $product, $supplierA, $customerB] = $this->context();
        $supplierD = $this->party(PartnerRole::Supplier, 'Supplier D');
        $customerC = $this->party(PartnerRole::Customer, 'Customer C');
        $deal = $this->directDeal($user, $product, $supplierA, $customerB, '10000', '1180', '12000', '1310');
        app(DealService::class)->addSource($deal, ['source_type' => 'SUPPLIER', 'supplier_id' => $supplierD->id, 'quantity_kg' => '10000', 'rate_per_ton' => '1220']);
        app(DealService::class)->addDestination($deal, ['destination_type' => 'CUSTOMER', 'customer_id' => $customerC->id, 'final_quantity_kg' => '8000', 'rate_per_ton' => '1320']);
        app(DealService::class)->complete($deal, $user->id);
        $deal->refresh();
        $this->assertDatabaseCount('deal_allocations', 0);

        $allocation = app(DealAllocationService::class);
        $sources = $deal->sources()->orderBy('id')->get();
        $destinations = $deal->destinations()->orderBy('id')->get();
        try {
            $allocation->allocate($deal, $sources[0], $destinations[0], '10001', $user->id);
            $this->fail('Over-allocation was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('deal_allocations', 0);
        }
        $allocation->allocate($deal, $sources[0], $destinations[0], '6000', $user->id);
        $allocation->allocate($deal, $sources[0], $destinations[1], '4000', $user->id);
        $allocation->allocate($deal, $sources[1], $destinations[0], '6000', $user->id);
        $allocation->allocate($deal, $sources[1], $destinations[1], '4000', $user->id);
        $settlement = app(DealSettlementService::class)->confirm($deal, $user->id);

        $this->assertSame('26280.00', $settlement->sale_revenue_usd);
        $this->assertSame('2280.00', $settlement->final_result_usd);
    }

    public function test_deal_expense_is_commercial_before_payment_and_never_fabricates_journal_money(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = $this->directDeal($user, $product, $supplier, $customer, '1000', '1200', '1000', '1310', true);
        $records = app(DealCommercialRecordService::class);
        $expense = $records->createExpense($deal, [
            'category' => 'FREIGHT', 'expense_date' => '2026-08-31', 'amount' => '7000',
            'currency' => Currency::Afn->value, 'exchange_rate_to_usd' => '70',
            'payee_id' => null, 'reference' => 'FRT-01', 'notes' => 'کرایه ثبت‌شده اما پرداخت‌نشده',
        ], $user->id);

        $this->assertSame('100.00', $expense->amount_usd);
        $this->assertSame('0.00', app(DealSettlementService::class)->preview($deal)['expenseTotal']);
        $records->confirmExpense($expense, $user->id);
        $settlement = app(DealSettlementService::class)->confirm($deal, $user->id);

        $this->assertSame('100.00', $settlement->direct_expenses_usd);
        $this->assertSame('10.00', $settlement->final_result_usd);
        $this->assertFalse($expense->fresh()->is_paid);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('money_movements', 0);
    }

    public function test_storage_claim_distinguishes_positive_negative_and_zero_residuals(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        foreach ([
            ['sale_rate' => '1000', 'expected' => '200.00'],
            ['sale_rate' => '1300', 'expected' => '-100.00'],
            ['sale_rate' => '1200', 'expected' => '0.00'],
        ] as $case) {
            $warehouse = Warehouse::factory()->create();
            $inDeal = app(DealService::class)->create([
                'deal_date' => '2026-08-30', 'product_id' => $product->id,
                'supplier_id' => $supplier->id, 'source_quantity_kg' => '1000', 'source_rate_per_ton' => '1200',
                'submit_action' => 'draft',
            ], $user->id);
            app(DealService::class)->addDestination($inDeal, ['destination_type' => 'STORAGE', 'warehouse_id' => $warehouse->id, 'final_quantity_kg' => '1000']);
            app(DealService::class)->complete($inDeal, $user->id);
            app(DealSettlementService::class)->confirm($inDeal, $user->id);

            $outDeal = app(DealService::class)->create(['deal_date' => '2026-08-31', 'product_id' => $product->id, 'submit_action' => 'draft'], $user->id);
            app(DealService::class)->addSource($outDeal, ['source_type' => 'STORAGE', 'warehouse_id' => $warehouse->id, 'quantity_kg' => '1000']);
            app(DealService::class)->addDestination($outDeal, ['destination_type' => 'CUSTOMER', 'customer_id' => $customer->id, 'final_quantity_kg' => '1000', 'rate_per_ton' => $case['sale_rate']]);
            app(DealService::class)->complete($outDeal, $user->id);
            app(DealSettlementService::class)->confirm($outDeal, $user->id);

            $claim = app(StorageClaimService::class)->position($warehouse->id, $product->id);
            $this->assertSame('0.000', $claim['quantity_kg']);
            $this->assertSame($case['expected'], $claim['claim_usd']);
            $this->assertSame($case['expected'] === '0.00' ? 'ZERO_CLOSED' : 'ZERO_RESIDUAL', $claim['state']);
        }
    }

    public function test_six_roles_preserve_settlement_authorization_boundaries(): void
    {
        [$manager, $product, $supplier, $customer] = $this->context();
        $deal = $this->directDeal($manager, $product, $supplier, $customer, '1000', '1200', '1000', '1310', true);

        foreach ([UserRole::GeneralManager, UserRole::Accountant] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('deals.settlement.prepare', $deal))->assertRedirect(route('deals.show', $deal));
        }
        foreach ([UserRole::SystemAdmin, UserRole::PurchaseSalesOfficer, UserRole::WarehouseOfficer, UserRole::TransportOfficer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('deals.settlement.prepare', $deal))->assertForbidden();
        }
    }

    public function test_deal_and_report_ui_separate_commercial_settlement_from_money_and_protect_profit_detail(): void
    {
        [$manager, $product, $supplier, $customer] = $this->context();
        $deal = $this->directDeal($manager, $product, $supplier, $customer, '19355', '1200', '19355', '1310', true);
        app(DealSettlementService::class)->confirm($deal, $manager->id);

        $this->actingAs($manager)->get(route('deals.show', $deal))
            ->assertOk()->assertSee(__('settlements.commercial_section'))->assertSee(__('settlements.financial_section'))
            ->assertSee('2,129.05')->assertSee(__('settlements.allocation_methods.AUTOMATIC'));
        $this->actingAs(User::factory()->create(['role' => UserRole::WarehouseOfficer]))->get(route('deals.show', $deal))
            ->assertOk()->assertDontSee(__('settlements.final_result'));
        $this->actingAs(User::factory()->create(['role' => UserRole::TransportOfficer]))->get(route('reports.settlements'))
            ->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]))->get(route('reports.settlements'))
            ->assertOk()->assertSee($deal->reference_code)->assertSee('2,129.05');
    }

    /** @return array{User,Product,BusinessPartner,BusinessPartner} */
    private function context(): array
    {
        return [
            User::factory()->create(['role' => UserRole::GeneralManager]),
            Product::factory()->create(),
            $this->party(PartnerRole::Supplier, 'Supplier A'),
            $this->party(PartnerRole::Customer, 'Customer B'),
        ];
    }

    private function directDeal(User $user, Product $product, BusinessPartner $supplier, BusinessPartner $customer, string $sourceQuantity, string $sourceRate, string $destinationQuantity, string $destinationRate, bool $complete = false): Deal
    {
        return app(DealService::class)->create([
            'deal_date' => '2026-08-31', 'product_id' => $product->id,
            'supplier_id' => $supplier->id, 'source_quantity_kg' => $sourceQuantity, 'source_rate_per_ton' => $sourceRate,
            'customer_id' => $customer->id, 'final_quantity_kg' => $destinationQuantity, 'destination_rate_per_ton' => $destinationRate,
            'submit_action' => $complete ? 'complete' : 'draft',
        ], $user->id);
    }

    private function party(PartnerRole $role, string $name): BusinessPartner
    {
        $partner = BusinessPartner::factory()->create(['name' => $name]);
        $partner->roles()->create(['role' => $role]);

        return $partner;
    }
}
