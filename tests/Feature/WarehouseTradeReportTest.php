<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class WarehouseTradeReportTest extends TestCase
{
    use CreatesTradeFixtures, RefreshDatabase;

    public function test_warehouse_purchases_use_stored_allocated_cost_and_keep_native_legacy_currency_separate(): void
    {
        $user = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct();
        $north = $this->tradeWarehouse();
        $south = $this->tradeWarehouse();
        $deal = Deal::create(['deal_date' => '2026-08-01', 'product_id' => $product->id, 'status' => 'COMPLETED']);
        $source = $deal->sources()->create(['source_type' => 'SUPPLIER', 'supplier_id' => $supplier->id, 'quantity_kg' => '1000', 'rate_per_ton' => '999', 'amount' => '1000.52']);
        foreach ([[$north, '400', '445.31'], [$south, '600', '555.21']] as [$store, $qty, $cost]) {
            $destination = $deal->destinations()->create(['destination_type' => 'STORAGE', 'warehouse_id' => $store->id, 'final_quantity_kg' => $qty]);
            $deal->allocations()->create(['deal_source_id' => $source->id, 'deal_destination_id' => $destination->id, 'quantity_kg' => $qty, 'allocated_cost_usd' => $cost]);
        }
        $legacy = Purchase::create(['purchase_date' => '2026-07-10', 'business_partner_id' => $supplier->id, 'currency' => 'AFN', 'status' => 'CONFIRMED', 'created_by' => $user->id]);
        $legacy->items()->create(['product_id' => $product->id, 'warehouse_id' => $north->id, 'quantity_kg' => '10', 'rate_per_ton' => '10000', 'amount' => '100']);

        $this->actingAs($user)->get(route('reports.purchases', ['warehouse_id' => $north->id]))
            ->assertOk()->assertViewHas('totalRecords', 2)->assertViewHas('totalQuantity', '410.000')
            ->assertSee('445.31 USD')->assertSee('100.00 AFN')->assertDontSee('545.31 USD')->assertDontSee('555.21 USD');
        $this->get(route('reports.purchases', ['warehouse_id' => $south->id]))
            ->assertOk()->assertViewHas('totalQuantity', '600.000')->assertSee('555.21 USD')->assertDontSee('445.31 USD');
        $this->get(route('reports.purchases'))->assertOk()->assertSee('1,000.52 USD')->assertSee('100.00 AFN');
        $this->actingAs($this->tradeUser(UserRole::WarehouseOfficer))->get(route('reports.purchases', ['warehouse_id' => $north->id]))
            ->assertOk()->assertDontSee('445.31')->assertDontSee('100.00 AFN');
        $this->assertSame('445.31', $deal->allocations()->orderBy('id')->first()->allocated_cost_usd);
    }

    public function test_filtered_sales_only_assign_stored_proceeds_for_a_wholly_attributable_delivery(): void
    {
        $user = $this->tradeUser(UserRole::GeneralManager);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct();
        $north = $this->tradeWarehouse();
        $south = $this->tradeWarehouse();
        $deal = Deal::create(['deal_date' => '2026-08-01', 'product_id' => $product->id, 'status' => 'COMPLETED']);
        $split = $deal->destinations()->create(['destination_type' => 'CUSTOMER', 'customer_id' => $customer->id, 'final_quantity_kg' => '900', 'amount' => '987.65']);
        $whole = $deal->destinations()->create(['destination_type' => 'CUSTOMER', 'customer_id' => $customer->id, 'final_quantity_kg' => '100', 'amount' => '250.25']);
        $fromNorth = $deal->sources()->create(['source_type' => 'STORAGE', 'warehouse_id' => $north->id, 'quantity_kg' => '450']);
        $fromSouth = $deal->sources()->create(['source_type' => 'STORAGE', 'warehouse_id' => $south->id, 'quantity_kg' => '550']);
        foreach ([[$fromNorth, $split, '350', '100.07'], [$fromSouth, $split, '550', '220.99'], [$fromNorth, $whole, '100', '12.08']] as [$src, $dest, $qty, $cost]) {
            $deal->allocations()->create(['deal_source_id' => $src->id, 'deal_destination_id' => $dest->id, 'quantity_kg' => $qty, 'allocated_cost_usd' => $cost]);
        }
        $legacy = Sale::create(['sale_date' => '2026-07-10', 'business_partner_id' => $customer->id, 'currency' => 'AFN', 'status' => 'CONFIRMED', 'created_by' => $user->id]);
        $legacy->items()->create(['product_id' => $product->id, 'warehouse_id' => $north->id, 'final_quantity_kg' => '10', 'rate_per_ton' => '7000', 'amount' => '70']);
        $this->actingAs($user)->get(route('reports.sales', ['warehouse_id' => $north->id]))
            ->assertOk()->assertViewHas('totalQuantity', '460.000')
            ->assertSee('250.25 USD')->assertSee('70.00 AFN')->assertSee('سهم مبلغ فروش ثبت نشده است')
            ->assertDontSee('987.65')->assertDontSee('100.07');
        $this->get(route('reports.sales', ['warehouse_id' => $south->id]))->assertOk()
            ->assertViewHas('totalQuantity', '550.000')->assertViewHas('totalAmounts', [])
            ->assertDontSee('987.65')->assertDontSee('220.99');
        $this->get(route('reports.sales'))->assertOk()->assertSee('1,237.90 USD')->assertSee('70.00 AFN');
        $this->assertSame('987.65', $split->fresh()->amount);
    }
}
