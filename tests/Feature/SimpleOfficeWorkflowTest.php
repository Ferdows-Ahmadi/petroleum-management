<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\FinancialTransactionType;
use App\Enums\PartnerRole;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Enums\WeighingStatus;
use App\Models\BusinessPartner;
use App\Models\FinancialTransaction;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\FinancialAccountBalanceService;
use App\Services\InventoryBalanceService;
use App\Services\PartnerLedgerService;
use App\Services\TradeDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFinanceFixtures;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class SimpleOfficeWorkflowTest extends TestCase
{
    use CreatesFinanceFixtures, CreatesTradeFixtures, RefreshDatabase;

    public function test_inline_supplier_purchase_confirms_inventory_and_six_thousand_payable(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $product = $this->tradeProduct('OFF-PU');
        $warehouse = $this->tradeWarehouse('ذخیره دفتر');
        $account = $this->financeAccount($manager, Currency::Usd, '10000');

        $response = $this->actingAs($manager)->post(route('office.transactions.purchases.store'), [
            'partner_name' => 'عرضه‌کننده خیالی دفتر',
            'partner_phone' => '0700000001',
            'purchase_date' => '1405/05/19',
            'reference_number' => 'OFFICE-PURCHASE-001',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_kg' => '10000',
            'rate_per_ton' => '1000',
            'payment_amount' => '4000',
            'financial_account_id' => $account->id,
            'submit_action' => 'confirm',
        ]);

        $response->assertRedirect(route('office.transactions', ['tab' => 'purchase']));
        $partner = BusinessPartner::where('name', 'عرضه‌کننده خیالی دفتر')->firstOrFail();
        $purchase = Purchase::where('reference_number', 'OFFICE-PURCHASE-001')->firstOrFail();
        $this->assertSame(TransactionStatus::Confirmed, $purchase->status);
        $this->assertTrue($partner->roles()->where('role', PartnerRole::Supplier->value)->exists());
        $this->assertSame('10000.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
        $this->assertSame('-6000.00', app(PartnerLedgerService::class)->balanceFor($partner));
        $this->assertDatabaseHas('financial_transactions', ['business_partner_id' => $partner->id, 'transaction_type' => FinancialTransactionType::PaymentPaid->value, 'amount' => 4000, 'status' => 'CONFIRMED']);
    }

    public function test_canonical_sale_is_entered_once_and_leaves_five_cent_receivable(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct('OFF-SL');
        $warehouse = $this->tradeWarehouse('ذخیره فروش');
        $this->addStock($manager, $supplier, $product, $warehouse, '30000');
        $account = $this->financeAccount($manager);

        $response = $this->actingAs($manager)->post(route('office.transactions.sales.store'), [
            'partner_name' => 'مشتری خیالی دفتر',
            'sale_date' => '1405/05/19',
            'reference_number' => 'OFFICE-SALE-001',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'requested_quantity_kg' => '20000',
            'rate_per_ton' => '1310',
            'weighing_at' => '1405/05/19 10:00',
            'first_weight_kg' => '34475',
            'second_weight_kg' => '15120',
            'ticket_number' => 'OFFICE-WG-001',
            'payment_amount' => '25355.00',
            'financial_account_id' => $account->id,
            'submit_action' => 'confirm',
        ]);

        $response->assertRedirect(route('office.transactions', ['tab' => 'sale']));
        $partner = BusinessPartner::where('name', 'مشتری خیالی دفتر')->firstOrFail();
        $sale = Sale::where('reference_number', 'OFFICE-SALE-001')->with('items.weighingRecord')->firstOrFail();
        $item = $sale->items->first();
        $this->assertSame(TransactionStatus::Confirmed, $sale->status);
        $this->assertSame('19355.000', $item->final_quantity_kg);
        $this->assertSame('25355.05', $item->amount);
        $this->assertSame(WeighingStatus::Finalized, $item->weighingRecord->status);
        $this->assertSame('10645.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
        $this->assertSame('0.05', app(PartnerLedgerService::class)->balanceFor($partner));
        $this->assertSame('25355.00', app(FinancialAccountBalanceService::class)->balanceFor($account));
    }

    public function test_existing_partner_is_reused_and_safely_gains_second_role(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $product = $this->tradeProduct('OFF-BOTH');
        $warehouse = $this->tradeWarehouse('ذخیره مشترک');
        $partner = $this->tradePartner([PartnerRole::Supplier]);

        $this->actingAs($manager)->post(route('office.transactions.sales.store'), [
            'partner_name' => $partner->name,
            'sale_date' => '1405/05/19',
            'reference_number' => 'OFFICE-BOTH-001',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'requested_quantity_kg' => '1000',
            'rate_per_ton' => '1200',
            'submit_action' => 'await_weighing',
        ])->assertRedirect();

        $this->assertSame(1, BusinessPartner::where('name', $partner->name)->count());
        $this->assertEqualsCanonicalizing(
            [PartnerRole::Supplier->value, PartnerRole::Customer->value],
            $partner->refresh()->roles->pluck('role.value')->all(),
        );
    }

    public function test_sale_can_wait_for_second_weight_and_be_finalized_later(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('OFF-WAIT');
        $warehouse = $this->tradeWarehouse('ذخیره انتظار');
        $this->addStock($manager, $supplier, $product, $warehouse, '30000');

        $this->actingAs($manager)->post(route('office.transactions.sales.store'), [
            'partner_name' => $customer->name,
            'sale_date' => '1405/05/19',
            'reference_number' => 'OFFICE-WAIT-001',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'requested_quantity_kg' => '20000',
            'rate_per_ton' => '1310',
            'weighing_at' => '1405/05/19 09:00',
            'first_weight_kg' => '34475',
            'submit_action' => 'await_weighing',
        ])->assertRedirect();

        $sale = Sale::where('reference_number', 'OFFICE-WAIT-001')->with('items.weighingRecord')->firstOrFail();
        $weighing = $sale->items->first()->weighingRecord;
        $this->assertSame(WeighingStatus::Draft, $weighing->status);
        $this->assertNull($weighing->second_weight_kg);
        $this->assertNull($weighing->net_weight_kg);

        $this->actingAs($manager)->from(route('office.transactions', ['tab' => 'sale']))
            ->post(route('weighings.finalize', $weighing))
            ->assertRedirect()
            ->assertSessionHasErrors('second_weight_kg');
        $this->assertSame(WeighingStatus::Draft, $weighing->refresh()->status);

        $this->actingAs($manager)->post(route('office.transactions.sales.weighing.complete', $sale), ['second_weight_kg' => '15120'])->assertRedirect();
        $sale->refresh()->load('items.weighingRecord');
        $this->assertSame(TransactionStatus::Confirmed, $sale->status);
        $this->assertSame('19355.000', $sale->items->first()->weighingRecord->net_weight_kg);
    }

    public function test_inactive_partner_is_not_reactivated_or_used_by_inline_entry(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $product = $this->tradeProduct('OFF-INACTIVE');
        $warehouse = $this->tradeWarehouse('ذخیره غیرفعال');
        $partner = $this->tradePartner([PartnerRole::Supplier]);
        $partner->update(['is_active' => false]);

        $this->actingAs($manager)->from(route('office.transactions'))->post(route('office.transactions.purchases.store'), [
            'partner_name' => $partner->name,
            'purchase_date' => '1405/05/19',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_kg' => '1000',
            'rate_per_ton' => '1000',
            'submit_action' => 'confirm',
        ])->assertRedirect(route('office.transactions'))->assertSessionHasErrors('partner_name');

        $this->assertFalse($partner->refresh()->is_active);
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_failed_simple_sale_rolls_back_inline_partner_and_all_derived_effects(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $product = $this->tradeProduct('OFF-ROLLBACK');
        $warehouse = $this->tradeWarehouse('ذخیره خالی');

        $this->actingAs($manager)->from(route('office.transactions', ['tab' => 'sale']))->post(route('office.transactions.sales.store'), [
            'partner_name' => 'مشتری خیالی برگشت',
            'sale_date' => '1405/05/19',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'requested_quantity_kg' => '20000',
            'rate_per_ton' => '1310',
            'weighing_at' => '1405/05/19 10:00',
            'first_weight_kg' => '34475',
            'second_weight_kg' => '15120',
            'submit_action' => 'confirm',
        ])->assertRedirect()->assertSessionHasErrors('stock');

        $this->assertDatabaseMissing('business_partners', ['name' => 'مشتری خیالی برگشت']);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('weighing_records', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_contextual_actions_preserve_role_authorization(): void
    {
        $tradeUser = $this->tradeUser(UserRole::PurchaseSalesOfficer);
        $transport = $this->tradeUser(UserRole::TransportOfficer);
        $warehouseUser = $this->tradeUser(UserRole::WarehouseOfficer);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('OFF-AUTH');
        $warehouse = $this->tradeWarehouse('ذخیره صلاحیت');
        $sale = app(TradeDraftService::class)->createSale($this->salePayload($customer, $product, $warehouse), $tradeUser->id);

        $this->actingAs($tradeUser)->post(route('office.transactions.sales.weighing.store', $sale), [
            'weighing_at' => '1405/05/19 10:00',
            'first_weight_kg' => '2000',
        ])->assertForbidden();

        $this->actingAs($transport)->post(route('office.transactions.purchases.store'), [])->assertForbidden();

        $this->actingAs($transport)->get(route('office.transactions', ['tab' => 'purchase']))
            ->assertOk()
            ->assertSee(route('office.transactions.sales.weighing.store', $sale), false)
            ->assertDontSee(route('office.transactions', ['tab' => 'purchase']), false)
            ->assertDontSee(route('sales.show', $sale), false)
            ->assertDontSee(__('trades.total_amount'))
            ->assertDontSee(__('office.partner_balance'));

        $this->actingAs($warehouseUser)->get(route('office.transactions'))
            ->assertOk()
            ->assertSee(route('sales.show', $sale), false)
            ->assertSee(__('trades.total_amount'))
            ->assertDontSee(__('office.partner_balance'));
    }

    public function test_inactive_partner_with_history_remains_visible_in_accounts_workspace(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct('OFF-HISTORY');
        $warehouse = $this->tradeWarehouse('ذخیره تاریخچه');
        $this->addStock($manager, $supplier, $product, $warehouse, '1000');
        $supplier->update(['is_active' => false]);

        $this->actingAs($manager)->get(route('office.accounts', ['partner_id' => $supplier->id]))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee(__('common.inactive'))
            ->assertSee('1,200.00');
    }

    public function test_manager_can_open_all_phase_five_work_areas(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);

        foreach ([
            route('dashboard') => __('navigation.dashboard'),
            route('office.transactions') => __('office.transactions'),
            route('office.accounts') => __('navigation.accounts'),
            route('finance.journal') => __('navigation.journal'),
            route('office.storage') => __('navigation.storage'),
            route('reports.index') => __('reports.title'),
            route('office.configuration') => __('navigation.settings'),
        ] as $url => $label) {
            $this->actingAs($manager)->get($url)->assertOk()->assertSee($label);
        }
    }

    public function test_simple_expense_entry_confirms_and_appears_in_the_daily_journal(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $account = $this->financeAccount($manager, Currency::Usd, '1000', '2026-08-01');

        $this->actingAs($manager)->post(route('office.finance.expenses.store'), [
            'financial_account_id' => $account->id,
            'transaction_date' => '1405/05/19',
            'amount' => '125.50',
            'currency' => 'USD',
            'expense_category' => 'OFFICE',
            'payee' => 'فروشگاه خیالی',
            'description' => 'مصرف آزمایشی دفتر',
        ])->assertRedirect(route('finance.journal'));

        $expense = FinancialTransaction::where('transaction_type', FinancialTransactionType::Expense->value)->firstOrFail();
        $this->assertSame('CONFIRMED', $expense->status->value);
        $this->assertSame('874.50', app(FinancialAccountBalanceService::class)->balanceFor($account));
        $this->actingAs($manager)->get(route('finance.journal', ['context' => 'account', 'account_id' => $account->id, 'date' => '1405/05/19']))
            ->assertOk()
            ->assertSee('125.50')
            ->assertSee('874.50');
    }
}
