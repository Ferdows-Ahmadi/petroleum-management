<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Enums\WeighingStatus;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DailyJournalService;
use App\Services\DealCommercialRecordService;
use App\Services\DealMoneySummaryService;
use App\Services\DealService;
use App\Services\DealSettlementService;
use App\Services\InventoryBalanceService;
use App\Services\InventoryValuationService;
use App\Services\MoneyMovementService;
use App\Services\PartnerLedgerService;
use App\Services\StorageCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionAuditTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        $supplier = BusinessPartner::factory()->create(['name' => 'Audit supplier']);
        $supplier->roles()->create(['role' => PartnerRole::Supplier]);
        $customer = BusinessPartner::factory()->create(['name' => 'Audit customer']);
        $customer->roles()->create(['role' => PartnerRole::Customer]);

        return [$user, Product::factory()->create(), $supplier, $customer];
    }

    private function data(Product $product, BusinessPartner $supplier, BusinessPartner $customer): array
    {
        return [
            'deal_date' => now()->toDateString(), 'product_id' => $product->id,
            'supplier_id' => $supplier->id, 'source_quantity_kg' => '10000', 'source_rate_per_ton' => '1000',
            'customer_id' => $customer->id, 'final_quantity_kg' => '10000', 'destination_rate_per_ton' => '1100',
            'submit_action' => 'complete',
        ];
    }

    public function test_thesis_scenario_agrees_across_deal_ledger_journal_dashboard_and_reports(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        $settlement = app(DealSettlementService::class)->confirm($deal, $user->id);
        $account = FinancialAccount::create(['name' => 'Audit cash', 'account_type' => 'CASH', 'currency' => 'USD', 'opening_balance' => '0', 'opening_balance_date' => now()->toDateString(), 'created_by' => $user->id, 'is_active' => true]);
        $money = app(MoneyMovementService::class)->create([
            'movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id,
            'financial_account_id' => $account->id, 'deal_id' => $deal->id,
            'movement_date' => now()->toDateString(), 'amount' => '5000', 'currency' => 'USD',
        ], $user->id);
        app(MoneyMovementService::class)->confirm($money, $user->id);

        $this->assertSame('10000.00', $deal->total_source_amount);
        $this->assertSame('11000.00', $deal->total_destination_amount);
        $this->assertSame('1000.00', $settlement->final_result_usd);
        $this->assertSame('6000.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertSame('-10000.00', app(PartnerLedgerService::class)->balanceFor($supplier));
        $this->assertSame('6000.00', app(DealMoneySummaryService::class)->forDeal($deal)['remaining_receivable']);
        $this->assertSame('5000.00', app(DailyJournalService::class)->forAccount($account, now()->toDateString())['received']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('money_movements', 1);
        $this->assertDatabaseCount('financial_transactions', 0);

        $this->actingAs($user)->get(route('reports.purchases'))->assertOk()->assertViewHas('totalAmount', '10000.00');
        $this->get(route('reports.sales'))->assertOk()->assertViewHas('totalAmount', '11000.00');
        $this->get(route('dashboard'))->assertOk()->assertViewHas('metrics', fn ($m) => $m['today_purchases'] === '10000.00' && $m['today_sales'] === '11000.00' && $m['today_received'] === '5000.00');
        $report = app(InventoryValuationService::class)->report();
        $this->assertSame('11000.00', $report['revenue']);
        $this->assertSame('10000.00', $report['cogs']);
        $this->assertSame('1000.00', $report['operating_result']);
    }

    public function test_deal_weighing_report_renders_and_filters_the_actual_deal_relationship(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create(array_replace($this->data($product, $supplier, $customer), [
            'weighing_mode' => 'DIRECT_NET', 'net_weight_kg' => '10000', 'weighing_at' => now()->toDateTimeString(),
        ]), $user->id);
        $this->actingAs($user)->get(route('reports.weighings', ['partner_id' => $customer->id, 'product_id' => $product->id]))
            ->assertOk()->assertSee($customer->name)->assertViewHas('totalNet', '10000.000');
        $this->get(route('reports.weighings', ['warehouse_id' => Warehouse::factory()->create()->id]))
            ->assertOk()->assertViewHas('totalRecords', 0);
    }

    public function test_generic_completion_cannot_bypass_pending_weighing_even_with_manual_final_quantity(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create(array_replace($this->data($product, $supplier, $customer), [
            'submit_action' => 'draft', 'weighing_mode' => 'TWO_STAGE', 'first_weight_kg' => '15000', 'weighing_at' => now()->toDateTimeString(),
        ]), $user->id);
        $this->actingAs($user)->post(route('deals.complete', $deal))->assertSessionHasErrors();
        $this->assertNotSame(DealStatus::Completed, $deal->fresh()->status);
        $this->assertSame(WeighingStatus::Draft, $deal->destinations->first()->weighingRecord->status);
    }

    public function test_invalid_decimal_input_returns_validation_instead_of_a_server_error(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        foreach (['abc', '1e5', ['bad']] as $bad) {
            $this->actingAs($user)->post(route('deals.store'), array_replace($this->data($product, $supplier, $customer), [
                'deal_date' => '1405/07/11', 'source_quantity_kg' => $bad,
            ]))->assertSessionHasErrors('source_quantity_kg');
        }
        $this->assertDatabaseCount('deals', 0);
    }

    public function test_deal_only_master_records_cannot_be_deleted_and_lose_history(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        $this->actingAs($user)->delete(route('partners.destroy', $supplier))->assertSessionHas('error');
        $this->delete(route('partners.destroy', $customer))->assertSessionHas('error');
        $this->delete(route('products.destroy', $product))->assertSessionHas('error');
        $this->assertNotSoftDeleted($supplier);
        $this->assertNotSoftDeleted($customer);
        $this->assertNotSoftDeleted($product);
    }

    public function test_operations_roles_cannot_read_prices_from_deal_details(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        foreach ([UserRole::WarehouseOfficer, UserRole::TransportOfficer] as $role) {
            $operator = User::factory()->create(['role' => $role]);
            $this->actingAs($operator)->get(route('deals.show', $deal))->assertOk()->assertDontSee('11,000')->assertDontSee('10,000.00');
        }
    }

    public function test_simple_draft_can_be_corrected_in_place_and_completed_without_duplicate_lines(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create(array_replace($this->data($product, $supplier, $customer), ['submit_action' => 'draft']), $user->id);
        $this->actingAs($user)->get(route('deals.edit', $deal))->assertOk()->assertSee('value="10000.000"', false);
        $this->put(route('deals.update', $deal), array_replace($this->data($product, $supplier, $customer), [
            'deal_date' => '1405/07/11', 'destination_rate_per_ton' => '1200',
        ]))->assertRedirect(route('deals.show', $deal));
        $this->assertSame(DealStatus::Completed, $deal->fresh()->status);
        $this->assertSame('12000.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertDatabaseCount('deals', 1);
        $this->assertDatabaseCount('deal_sources', 1);
        $this->assertDatabaseCount('deal_destinations', 1);
        $this->get(route('deals.edit', $deal))->assertForbidden();
        $this->put(route('deals.update', $deal), array_replace($this->data($product, $supplier, $customer), ['deal_date' => '1405/07/11']))->assertSessionHasErrors();
        $this->assertSame('12000.00', app(PartnerLedgerService::class)->balanceFor($customer));
    }

    public function test_duplicate_form_post_creates_one_deal_and_rejects_changed_payload(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $data = array_replace($this->data($product, $supplier, $customer), ['deal_date' => '1405/07/11', 'operation_key' => (string) Str::uuid()]);
        $first = $this->actingAs($user)->post(route('deals.store'), $data)->assertRedirect();
        $this->post(route('deals.store'), $data)->assertRedirect($first->headers->get('Location'));
        $this->assertDatabaseCount('deals', 1);
        $this->assertDatabaseCount('operation_receipts', 1);
        $this->post(route('deals.store'), array_replace($data, ['destination_rate_per_ton' => '1200']))->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('deals', 1);
    }

    public function test_duplicate_receipt_is_one_real_money_event_and_ledger_effect(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        $account = FinancialAccount::create(['name' => 'Audit cash', 'account_type' => 'CASH', 'currency' => 'USD', 'opening_balance' => '0', 'opening_balance_date' => now()->toDateString(), 'created_by' => $user->id, 'is_active' => true]);
        $data = ['movement_type' => 'CUSTOMER_RECEIPT', 'counterparty_id' => $customer->id, 'deal_id' => $deal->id,
            'financial_account_id' => $account->id, 'movement_date' => '1405/07/11', 'currency' => 'USD', 'amount' => '5000',
            'confirm_now' => 1, 'operation_key' => (string) Str::uuid()];
        $this->actingAs($user)->post(route('money-movements.store'), $data)->assertRedirect();
        $this->post(route('money-movements.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('money_movements', 1);
        $this->assertSame('6000.00', app(PartnerLedgerService::class)->balanceFor($customer));
    }

    public function test_validation_preserves_solar_hijri_dates_and_other_input(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $this->actingAs($user)->post(route('deals.store'), array_replace($this->data($product, $supplier, $customer), [
            'deal_date' => '1405/07/11', 'source_quantity_kg' => 'invalid', 'notes' => 'Preserve this note',
        ]))->assertSessionHasErrors('source_quantity_kg')->assertSessionHasInput('deal_date', '1405/07/11')->assertSessionHasInput('notes', 'Preserve this note');
    }

    public function test_settlement_revisions_do_not_double_profit_or_remove_an_effective_confirmed_result(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        $service = app(DealSettlementService::class);
        $service->confirm($deal, $user->id);
        $service->prepare($deal, $user->id);
        $this->actingAs($user)->get(route('reports.settlements'))->assertOk()->assertViewHas('finalResult', '1000.00');
        $this->get(route('reports.settlements', ['status' => 'DRAFT']))->assertOk()->assertSee('badge badge-amber', false)->assertViewHas('finalResult', '1000.00');
        $this->assertSame('1000.00', app(InventoryValuationService::class)->report()['operating_result']);
        $service->confirm($deal, $user->id);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('todaySettlementResult', '1000.00');
    }

    public function test_completed_but_unsettled_deal_marks_profit_report_incomplete(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        $report = app(InventoryValuationService::class)->report();
        $this->assertSame('11000.00', $report['revenue']);
        $this->assertSame(1, $report['unsettled_deals_count']);
        $this->assertNull($report['operating_result']);
    }

    public function test_mixed_supplier_and_storage_sources_allocate_both_costs_after_hydration(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $warehouse = Warehouse::factory()->create();
        $input = app(DealService::class)->create(['deal_date' => now()->toDateString(), 'product_id' => $product->id, 'submit_action' => 'draft'], $user->id);
        app(DealService::class)->addSource($input, ['source_type' => 'SUPPLIER', 'supplier_id' => $supplier->id, 'quantity_kg' => '1000', 'rate_per_ton' => '1000']);
        app(DealService::class)->addDestination($input, ['destination_type' => 'STORAGE', 'warehouse_id' => $warehouse->id, 'final_quantity_kg' => '1000']);
        app(DealService::class)->complete($input, $user->id);
        app(DealSettlementService::class)->confirm($input, $user->id);

        $out = app(DealService::class)->create(['deal_date' => now()->toDateString(), 'product_id' => $product->id, 'submit_action' => 'draft'], $user->id);
        app(DealService::class)->addSource($out, ['source_type' => 'SUPPLIER', 'supplier_id' => $supplier->id, 'quantity_kg' => '1000', 'rate_per_ton' => '1200']);
        app(DealService::class)->addSource($out, ['source_type' => 'STORAGE', 'warehouse_id' => $warehouse->id, 'quantity_kg' => '1000']);
        app(DealService::class)->addDestination($out, ['destination_type' => 'CUSTOMER', 'customer_id' => $customer->id, 'final_quantity_kg' => '2000', 'rate_per_ton' => '1400']);
        app(DealService::class)->complete($out, $user->id);
        $settlement = app(DealSettlementService::class)->confirm($out, $user->id);
        $this->assertSame('600.00', $settlement->final_result_usd);
        $this->assertSame('2200.00', $out->fresh()->total_source_cost);
        $this->assertSame(2, $out->allocations()->count());
        $this->assertSame('0.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
        $report = app(InventoryValuationService::class)->report();
        $this->assertSame('2800.00', $report['revenue']);
        $this->assertSame('2200.00', $report['cogs']);
        $this->assertSame('600.00', $report['operating_result']);
    }

    public function test_split_allocations_conserve_source_cents_instead_of_rounding_each_cost_up(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create(array_replace($this->data($product, $supplier, $customer), [
            'source_quantity_kg' => '0.003', 'source_rate_per_ton' => '5000', 'final_quantity_kg' => '0.001',
            'destination_rate_per_ton' => '10000', 'submit_action' => 'draft',
        ]), $user->id);
        for ($i = 0; $i < 2; $i++) {
            app(DealService::class)->addDestination($deal, ['destination_type' => 'CUSTOMER', 'customer_id' => $customer->id, 'final_quantity_kg' => '0.001', 'rate_per_ton' => '10000']);
        }
        app(DealService::class)->complete($deal, $user->id);
        $settlement = app(DealSettlementService::class)->confirm($deal, $user->id);
        $totalCost = $deal->allocations()->get()->reduce(fn ($sum, $row) => bcadd($sum, $row->allocated_cost_usd, 2), '0.00');
        $this->assertSame('0.02', $settlement->source_value_usd);
        $this->assertSame('0.02', $totalCost);
        $this->assertSame('0.01', $settlement->final_result_usd);
    }

    public function test_draft_commercial_mistakes_can_be_removed_but_confirmed_history_is_protected(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        $service = app(DealCommercialRecordService::class);
        $expense = $service->createExpense($deal, ['category' => 'FREIGHT', 'expense_date' => now()->toDateString(), 'amount' => '10', 'currency' => 'USD'], $user->id);
        $other = Deal::create(['deal_date' => now()->toDateString(), 'status' => 'DRAFT']);
        $this->actingAs($user)->delete(route('deals.expenses.destroy', [$other, $expense]))->assertNotFound();
        $this->delete(route('deals.expenses.destroy', [$deal, $expense]))->assertRedirect();
        $this->assertDatabaseCount('deal_expenses', 0);
        $expense = $service->createExpense($deal, ['category' => 'FREIGHT', 'expense_date' => now()->toDateString(), 'amount' => '10', 'currency' => 'USD'], $user->id);
        $service->confirmExpense($expense, $user->id);
        $this->delete(route('deals.expenses.destroy', [$deal, $expense]))->assertSessionHasErrors('status');
        $this->assertDatabaseHas('deal_expenses', ['id' => $expense->id, 'status' => 'CONFIRMED']);
    }

    public function test_warehouse_legacy_pages_and_reports_do_not_reveal_prices_or_private_purchase_bills(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $warehouse = Warehouse::factory()->create();
        $purchase = Purchase::create(['business_partner_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'currency' => 'USD', 'status' => 'DRAFT', 'total_amount' => '9876.54', 'created_by' => $user->id]);
        $purchase->items()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity_kg' => '1000', 'rate_per_ton' => '9876.5400', 'amount' => '9876.54']);
        $operator = User::factory()->create(['role' => UserRole::WarehouseOfficer]);
        foreach ([route('purchases.index'), route('purchases.show', $purchase), route('reports.purchases')] as $url) {
            $this->actingAs($operator)->get($url)->assertOk()->assertDontSee('9,876.54');
        }
        $this->get(route('purchases.ticket', $purchase))->assertForbidden();
    }

    public function test_contextual_receipt_defaults_to_deal_customer_and_keeps_backend_party_validation(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        $this->actingAs($user)->get(route('deals.show', $deal))->assertOk()
            ->assertSee('name="counterparty_id" value="'.$customer->id.'"', false);
    }

    public function test_draft_split_line_mistakes_can_be_removed_without_editing_completed_history(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create(array_replace($this->data($product, $supplier, $customer), ['submit_action' => 'draft']), $user->id);
        $extra = app(DealService::class)->addSource($deal, ['source_type' => 'SUPPLIER', 'supplier_id' => $supplier->id, 'quantity_kg' => '10', 'rate_per_ton' => '1000']);
        $other = Deal::create(['deal_date' => now()->toDateString(), 'status' => 'DRAFT']);
        $this->actingAs($user)->delete(route('deals.sources.destroy', [$other, $extra]))->assertNotFound();
        $this->delete(route('deals.sources.destroy', [$deal, $extra]))->assertRedirect();
        $this->assertSame(1, $deal->sources()->count());
        $this->assertSame('PENDING', app(DealMoneySummaryService::class)->forDeal($deal)['settlement_state']);
        app(DealService::class)->complete($deal, $user->id);
        $this->delete(route('deals.sources.destroy', [$deal, $deal->sources()->first()]))->assertSessionHasErrors('deal');
        $this->assertSame('11000.00', app(PartnerLedgerService::class)->balanceFor($customer));
    }

    public function test_paid_expense_is_neither_hidden_by_a_draft_link_nor_counted_twice_after_settlement(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create($this->data($product, $supplier, $customer), $user->id);
        $account = FinancialAccount::create(['name' => 'Audit cash', 'account_type' => 'CASH', 'currency' => 'USD', 'opening_balance' => '1000', 'opening_balance_date' => now()->toDateString(), 'created_by' => $user->id, 'is_active' => true]);
        $payment = FinancialTransaction::create(['financial_account_id' => $account->id, 'transaction_date' => now()->toDateString(), 'transaction_type' => 'EXPENSE', 'amount' => '10', 'currency' => 'USD', 'amount_usd' => '10', 'expense_category' => 'TRANSPORT', 'status' => 'CONFIRMED', 'created_by' => $user->id]);
        $service = app(DealCommercialRecordService::class);
        $expense = $service->createExpense($deal, ['category' => 'FREIGHT', 'expense_date' => now()->toDateString(), 'amount' => '10', 'currency' => 'USD', 'financial_transaction_id' => $payment->id], $user->id);
        $this->assertSame('10.00', app(InventoryValuationService::class)->report()['expenses']);
        $service->confirmExpense($expense, $user->id);
        app(DealSettlementService::class)->confirm($deal, $user->id);
        $report = app(InventoryValuationService::class)->report();
        $this->assertSame('10.00', $report['expenses']);
        $this->assertSame('990.00', $report['operating_result']);
    }

    public function test_stock_costing_and_valuation_use_the_same_business_dates_for_late_entered_records(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $warehouse = Warehouse::factory()->create();
        foreach ([['2026-10-01', '1000'], ['2026-10-03', '3000']] as [$date, $cost]) {
            $purchase = Purchase::create(['business_partner_id' => $supplier->id, 'purchase_date' => $date, 'currency' => 'USD', 'status' => 'CONFIRMED', 'total_amount' => $cost, 'created_by' => $user->id]);
            $item = $purchase->items()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity_kg' => '1000', 'rate_per_ton' => $cost, 'amount' => $cost]);
            InventoryMovement::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'movement_type' => 'PURCHASE_IN', 'quantity_kg' => '1000', 'occurred_at' => '2026-10-03 12:00:00', 'purchase_item_id' => $item->id]);
        }
        $sale = Sale::create(['business_partner_id' => $customer->id, 'sale_date' => '2026-10-02', 'currency' => 'USD', 'status' => 'CONFIRMED', 'total_amount' => '1000', 'created_by' => $user->id]);
        $item = $sale->items()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'final_quantity_kg' => '500', 'rate_per_ton' => '2000', 'amount' => '1000']);
        InventoryMovement::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'movement_type' => 'SALE_OUT', 'quantity_kg' => '500', 'occurred_at' => '2026-10-03 13:00:00', 'sale_item_id' => $item->id]);
        $position = app(StorageCostService::class)->position($warehouse->id, $product->id);
        $this->assertSame('1500.000', $position['quantity_kg']);
        $this->assertSame('3500.00', $position['value_usd']);
        $this->assertSame($position['value_usd'], app(InventoryValuationService::class)->report()['inventory_value']);
    }

    public function test_invalid_second_weight_remains_visible_without_posting_any_accounting(): void
    {
        [$user, $product, $supplier, $customer] = $this->context();
        $deal = app(DealService::class)->create(array_replace($this->data($product, $supplier, $customer), [
            'submit_action' => 'draft', 'weighing_mode' => 'TWO_STAGE', 'first_weight_kg' => '15000', 'weighing_at' => now()->toDateTimeString(),
        ]), $user->id);
        $this->actingAs($user)->from(route('deals.show', $deal))->post(route('deals.weighing.complete', $deal), ['second_weight_kg' => '16000'])
            ->assertRedirect(route('deals.show', $deal))->assertSessionHasErrors('first_weight_kg')->assertSessionHasInput('second_weight_kg', '16000');
        $this->get(route('deals.show', $deal))->assertOk()->assertSee('value="16000"', false);
        $this->assertSame('0.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertDatabaseCount('inventory_movements', 0);
    }
}
