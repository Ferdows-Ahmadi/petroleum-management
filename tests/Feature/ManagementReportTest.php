<?php

namespace Tests\Feature;

use App\Enums\CapitalTransactionType;
use App\Enums\FinancialTransactionType;
use App\Enums\InventoryMovementType;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\CapitalPartner;
use App\Models\InventoryMovement;
use App\Services\CapitalTransactionService;
use App\Services\InventoryValuationService;
use App\Services\TradeConfirmationService;
use App\Services\TradeEntryService;
use App\Services\WeighingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class ManagementReportTest extends TestCase
{
    use CreatesFinanceFixtures, CreatesTradeFixtures, RefreshDatabase;

    public function test_weighted_average_cost_and_profit_are_calculated_exactly(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '1000', '1000', 'WAC-P1');
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-02', '1000', '2000', 'WAC-P2');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-03', '500', '3000', 'WAC-S1');

        $report = app(InventoryValuationService::class)->report();

        $this->assertTrue($report['is_complete']);
        $this->assertSame('1500.00', $report['revenue']);
        $this->assertSame('750.00', $report['cogs']);
        $this->assertSame('750.00', $report['gross_profit']);
        $this->assertSame('1500.000', $report['streams']->first()['quantity_kg']);
        $this->assertSame('2250.00', $report['streams']->first()['value_usd']);
    }

    public function test_alternating_purchases_and_sales_use_chronological_moving_average(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '1000', '1000', 'ALT-P1');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-02', '500', '2000', 'ALT-S1');
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-03', '500', '3000', 'ALT-P2');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-04', '500', '4000', 'ALT-S2');

        $report = app(InventoryValuationService::class)->report();

        $this->assertSame('3000.00', $report['revenue']);
        $this->assertSame('1500.00', $report['cogs']);
        $this->assertSame('1500.00', $report['gross_profit']);
        $this->assertSame('500.000', $report['streams']->first()['quantity_kg']);
        $this->assertSame('1000.00', $report['streams']->first()['value_usd']);
    }

    public function test_same_day_purchases_precede_sales_and_recalculation_is_deterministic(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '1000', '1000', 'SAME-P1');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-02', '500', '3000', 'SAME-S1');
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-02', '1000', '3000', 'SAME-P2');

        $first = app(InventoryValuationService::class)->report();
        $second = app(InventoryValuationService::class)->report();

        $this->assertSame('1000.00', $first['cogs']);
        $this->assertSame('3000.00', $first['streams']->first()['value_usd']);
        $this->assertSame($first['cogs'], $second['cogs']);
        $this->assertSame($first['sales']->pluck('cogs_usd')->all(), $second['sales']->pluck('cogs_usd')->all());
    }

    public function test_backdated_purchase_is_reprocessed_by_effective_business_date(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-10', '1000', '1000', 'BACK-P1');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-11', '500', '2000', 'BACK-S1');
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-09', '1000', '3000', 'BACK-P2');

        $report = app(InventoryValuationService::class)->report();

        $this->assertSame('1000.00', $report['cogs']);
        $this->assertSame('3000.00', $report['streams']->first()['value_usd']);
    }

    public function test_confirmed_expenses_reduce_operating_result_but_drafts_do_not(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '1000', '1000', 'EXP-P1');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-03', '500', '2000', 'EXP-S1');
        $account = $this->financeAccount($user);
        $payload = $this->paymentPayload($account, $customer, '100', '2026-08-03');
        unset($payload['business_partner_id']);
        $payload += ['expense_category' => 'OFFICE', 'payee' => 'Test Payee'];
        $this->financeTransaction($payload, FinancialTransactionType::Expense, $user, true);
        $payload['reference'] = 'DRAFT-EXPENSE';
        $payload['amount'] = '500';
        $this->financeTransaction($payload, FinancialTransactionType::Expense, $user, false);

        $report = app(InventoryValuationService::class)->report();

        $this->assertSame('100.00', $report['expenses']);
        $this->assertSame('400.00', $report['operating_result']);
    }

    public function test_receipts_and_payments_are_excluded_from_profit_loss(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '1000', '1000', 'CASH-P1');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-03', '500', '2000', 'CASH-S1');
        $account = $this->financeAccount($user);
        $this->financeTransaction($this->paymentPayload($account, $customer, '900'), FinancialTransactionType::PaymentReceived, $user, true);
        $this->financeTransaction($this->paymentPayload($account, $supplier, '700'), FinancialTransactionType::PaymentPaid, $user, true);

        $report = app(InventoryValuationService::class)->report();

        $this->assertSame('1000.00', $report['revenue']);
        $this->assertSame('500.00', $report['cogs']);
        $this->assertSame('0.00', $report['expenses']);
    }

    public function test_capital_contribution_is_not_profit_report_revenue(): void
    {
        $user = $this->financeUser();
        $account = $this->financeAccount($user);
        $owner = CapitalPartner::create(['name' => 'Excluded Capital Owner', 'is_active' => true]);
        $capital = app(CapitalTransactionService::class)->create(['capital_partner_id' => $owner->id, 'financial_account_id' => $account->id, 'transaction_date' => '2026-08-10', 'transaction_type' => CapitalTransactionType::Contribution->value, 'adjustment_direction' => null, 'amount_usd' => '5000', 'notes' => null], $user->id);
        app(CapitalTransactionService::class)->confirm($capital, $user->id);

        $report = app(InventoryValuationService::class)->report();

        $this->assertSame('0.00', $report['revenue']);
        $this->assertSame('0.00', $report['expenses']);
    }

    public function test_period_filter_uses_prior_stock_cost_but_only_period_revenue(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-07-01', '1000', '1200', 'CUT-P1');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-05', '250', '2000', 'CUT-S1');

        $report = app(InventoryValuationService::class)->report('2026-08-01', '2026-08-31');

        $this->assertSame('500.00', $report['revenue']);
        $this->assertSame('300.00', $report['cogs']);
    }

    public function test_product_and_warehouse_streams_are_isolated(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $otherWarehouse = $this->tradeWarehouse('Other');
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '1000', '1000', 'ISO-P1');
        $this->purchase($user, $supplier, $product, $otherWarehouse, '2026-08-01', '1000', '3000', 'ISO-P2');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-02', '500', '2000', 'ISO-S1');

        $report = app(InventoryValuationService::class)->report(null, null, $product->id, $warehouse->id);

        $this->assertCount(1, $report['streams']);
        $this->assertSame('500.00', $report['cogs']);
        $this->assertSame($warehouse->id, $report['streams']->first()['warehouse']->id);
    }

    public function test_unsupported_sale_movement_marks_report_incomplete(): void
    {
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        InventoryMovement::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'movement_type' => InventoryMovementType::SaleOut, 'quantity_kg' => '10', 'occurred_at' => '2026-08-05 10:00:00', 'notes' => 'Unsupported source']);

        $report = app(InventoryValuationService::class)->report();

        $this->assertFalse($report['is_complete']);
        $this->assertSame(1, $report['unsupported_movements_count']);
        $this->assertSame(1, $report['unvalued_sales_count']);
        $this->assertNull($report['cogs']);
        $this->assertNull($report['operating_result']);
    }

    public function test_report_routes_apply_role_and_filter_boundaries(): void
    {
        $viewer = $this->tradeUser(UserRole::WarehouseOfficer);
        $this->actingAs($viewer)->get(route('reports.purchases', ['date_from' => '1405/05/01']))->assertOk();
        $this->actingAs($viewer)->get(route('reports.profit-loss'))->assertForbidden();
        $accountant = $this->tradeUser(UserRole::Accountant);
        $this->actingAs($accountant)->get(route('reports.profit-loss'))->assertOk();
    }

    public function test_purchase_report_filters_by_jalali_period_and_partner(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $otherSupplier = $this->tradePartner([PartnerRole::Supplier]);
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-10', '1000', '1000', 'FILTER-P1');
        $this->purchase($user, $otherSupplier, $product, $warehouse, '2026-08-11', '2000', '1000', 'FILTER-P2');

        $this->actingAs($user)->get(route('reports.purchases', ['date_from' => '1405/05/19', 'date_to' => '1405/05/19', 'partner_id' => $supplier->id]))
            ->assertOk()
            ->assertViewHas('records', fn ($records) => $records->count() === 1 && $records->first()->business_partner_id === $supplier->id)
            ->assertSee('value="1405/05/19"', false)
            ->assertDontSee('value="2026-08-10"', false);
    }

    public function test_major_report_filters_redisplay_jalali_instead_of_gregorian_dates(): void
    {
        $user = $this->financeUser();

        foreach (['reports.expenses', 'reports.weighings', 'reports.profit-loss', 'reports.capital'] as $routeName) {
            $this->actingAs($user)->get(route($routeName, ['date_from' => '1405/05/19', 'date_to' => '1405/05/19']))
                ->assertOk()
                ->assertSee('value="1405/05/19"', false)
                ->assertDontSee('value="2026-08-10"', false);
        }
    }

    public function test_sales_report_filters_and_totals_final_weighed_quantity(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '3000', '1000', 'SALE-FILTER-P');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-10', '500', '2000', 'SALE-FILTER-S');

        $this->actingAs($user)->get(route('reports.sales', ['product_id' => $product->id]))
            ->assertOk()->assertViewHas('totalQuantity', '500.000')->assertViewHas('totalAmount', '1000.00');
    }

    public function test_trade_report_headlines_default_to_confirmed_but_explicit_status_uses_selected_records(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '1000', '1000', 'TOTAL-CONFIRMED');
        $draftPayload = $this->purchasePayload($supplier, $product, $warehouse, '900', '1000');
        $draftPayload['reference_number'] = 'TOTAL-DRAFT';
        app(TradeEntryService::class)->createPurchase($draftPayload, $user->id)->update(['status' => 'DRAFT']);

        $this->actingAs($user)->get(route('reports.purchases'))
            ->assertOk()
            ->assertViewHas('records', fn ($records) => $records->count() === 2)
            ->assertViewHas('totalRecords', 1)
            ->assertViewHas('totalQuantity', '1000.000')
            ->assertViewHas('totalAmount', '1000.00')
            ->assertViewHas('totalsConfirmedOnly', true);

        $this->actingAs($user)->get(route('reports.purchases', ['status' => 'DRAFT']))
            ->assertOk()
            ->assertViewHas('totalRecords', 1)
            ->assertViewHas('totalQuantity', '900.000')
            ->assertViewHas('totalAmount', '900.00')
            ->assertViewHas('totalsConfirmedOnly', false);
    }

    public function test_weighing_report_headline_defaults_to_finalized_records(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '2000', '1000', 'WG-TOTAL-P');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-02', '500', '2000', 'WG-TOTAL-S');
        $draftPayload = $this->salePayload($customer, $product, $warehouse, '300', '2000');
        $draftPayload['reference_number'] = 'WG-DRAFT-S';
        $draftSale = app(TradeEntryService::class)->createSale($draftPayload, $user->id);
        app(WeighingService::class)->create([
            'sale_item_id' => $draftSale->items()->firstOrFail()->id,
            'ticket_number' => 'WG-DRAFT',
            'station_name' => 'Test',
            'weighing_at' => '2026-08-03 10:00:00',
            'first_weight_kg' => '1300',
            'second_weight_kg' => '1000',
            'vehicle_number' => null,
            'driver_name' => null,
            'notes' => null,
        ], $user->id);

        $this->actingAs($user)->get(route('reports.weighings'))
            ->assertOk()
            ->assertViewHas('weighings', fn ($records) => $records->count() === 2)
            ->assertViewHas('totalRecords', 1)
            ->assertViewHas('totalNet', '500.000')
            ->assertViewHas('totalsConfirmedOnly', true);

        $this->actingAs($user)->get(route('reports.weighings', ['status' => 'DRAFT']))
            ->assertOk()
            ->assertViewHas('totalRecords', 1)
            ->assertViewHas('totalNet', '300.000')
            ->assertViewHas('totalsConfirmedOnly', false);
    }

    public function test_capital_report_filters_by_jalali_date_range(): void
    {
        $user = $this->financeUser();
        $owner = CapitalPartner::create(['name' => 'Period Owner', 'is_active' => true]);
        foreach ([['2026-08-09', '40'], ['2026-08-10', '80']] as [$date, $amount]) {
            $transaction = app(CapitalTransactionService::class)->create([
                'capital_partner_id' => $owner->id,
                'financial_account_id' => null,
                'transaction_date' => $date,
                'transaction_type' => CapitalTransactionType::ProfitAllocation->value,
                'adjustment_direction' => null,
                'amount_usd' => $amount,
                'notes' => null,
            ], $user->id);
            app(CapitalTransactionService::class)->confirm($transaction, $user->id);
        }

        $this->actingAs($user)->get(route('reports.capital', ['date_from' => '1405/05/19', 'date_to' => '1405/05/19']))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->first()['balance'] === '80.00' && $rows->first()['closing'] === '120.00')
            ->assertSee('value="1405/05/19"', false)
            ->assertDontSee('value="2026-08-10"', false);
    }

    public function test_expense_report_total_counts_only_confirmed_expenses(): void
    {
        $user = $this->financeUser();
        $partner = $this->tradePartner([PartnerRole::Customer]);
        $account = $this->financeAccount($user);
        $payload = $this->paymentPayload($account, $partner, '75');
        unset($payload['business_partner_id']);
        $payload += ['expense_category' => 'OFFICE', 'payee' => 'Test'];
        $this->financeTransaction($payload, FinancialTransactionType::Expense, $user, true);
        $payload['reference'] = 'DRAFT-REPORT-EXPENSE';
        $payload['amount'] = '900';
        $this->financeTransaction($payload, FinancialTransactionType::Expense, $user);

        $this->actingAs($user)->get(route('reports.expenses'))->assertOk()->assertViewHas('confirmedTotal', '75.00');
    }

    public function test_weighing_and_capital_reports_expose_derived_totals(): void
    {
        [$user, $supplier, $customer, $product, $warehouse] = $this->tradeContext();
        $this->purchase($user, $supplier, $product, $warehouse, '2026-08-01', '1000', '1000', 'REPORT-P');
        $this->sale($user, $customer, $product, $warehouse, '2026-08-10', '250', '2000', 'REPORT-S');
        $owner = CapitalPartner::create(['name' => 'Report Owner', 'is_active' => true]);
        $capital = app(CapitalTransactionService::class)->create(['capital_partner_id' => $owner->id, 'financial_account_id' => null, 'transaction_date' => '2026-08-10', 'transaction_type' => CapitalTransactionType::ProfitAllocation->value, 'adjustment_direction' => null, 'amount_usd' => '80', 'notes' => null], $user->id);
        app(CapitalTransactionService::class)->confirm($capital, $user->id);

        $this->actingAs($user)->get(route('reports.weighings'))->assertOk()->assertViewHas('totalNet', '250.000');
        $this->actingAs($user)->get(route('reports.capital'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->first()['balance'] === '80.00' && $rows->first()['closing'] === '80.00');
    }

    /** @return array{mixed, mixed, mixed, mixed, mixed} */
    private function tradeContext(): array
    {
        return [$this->tradeUser(UserRole::Accountant), $this->tradePartner([PartnerRole::Supplier]), $this->tradePartner([PartnerRole::Customer]), $this->tradeProduct(), $this->tradeWarehouse()];
    }

    private function purchase($user, $supplier, $product, $warehouse, string $date, string $quantity, string $rate, string $reference): void
    {
        $payload = $this->purchasePayload($supplier, $product, $warehouse, $quantity, $rate);
        $payload['purchase_date'] = $date;
        $payload['reference_number'] = $reference;
        $purchase = app(TradeEntryService::class)->createPurchase($payload, $user->id);
        app(TradeConfirmationService::class)->confirmPurchase($purchase, $user->id);
    }

    private function sale($user, $customer, $product, $warehouse, string $date, string $quantity, string $rate, string $reference): void
    {
        $payload = $this->salePayload($customer, $product, $warehouse, $quantity, $rate);
        $payload['sale_date'] = $date;
        $payload['reference_number'] = $reference;
        $sale = app(TradeEntryService::class)->createSale($payload, $user->id);
        $this->finalizeSaleItemWeighing($sale->items->first(), $user, bcadd($quantity, '1000', 3));
        app(TradeConfirmationService::class)->confirmSale($sale, $user->id);
    }
}
