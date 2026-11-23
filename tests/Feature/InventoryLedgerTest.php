<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Services\InventoryBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use CreatesTradeFixtures, RefreshDatabase;

    public function test_inventory_balance_is_derived_from_in_and_out_movements(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        $this->addStock($user, $supplier, $product, $warehouse, '1500.500');
        $this->actingAs($user)->post(route('sales.store'), $this->salePayload($customer, $product, $warehouse, '400.250'));
        $sale = Sale::firstOrFail();
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user);
        $this->post(route('sales.confirm', $sale));

        $this->assertSame('1100.250', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_product_balances_remain_separate(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $warehouse = $this->tradeWarehouse();
        $diesel = $this->tradeProduct('DSL');
        $petrol = $this->tradeProduct('PTR');
        $this->addStock($user, $supplier, $diesel, $warehouse, '900');
        $this->addStock($user, $supplier, $petrol, $warehouse, '350');

        $balances = app(InventoryBalanceService::class);
        $this->assertSame('900.000', $balances->balanceFor($warehouse->id, $diesel->id));
        $this->assertSame('350.000', $balances->balanceFor($warehouse->id, $petrol->id));
    }

    public function test_warehouse_balances_remain_separate(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct();
        $central = $this->tradeWarehouse('ذخیره مرکزی آزمایشی');
        $secondary = $this->tradeWarehouse('ذخیره دوم آزمایشی');
        $this->addStock($user, $supplier, $product, $central, '1000');
        $this->addStock($user, $supplier, $product, $secondary, '250');

        $balances = app(InventoryBalanceService::class);
        $this->assertSame('1000.000', $balances->balanceFor($central->id, $product->id));
        $this->assertSame('250.000', $balances->balanceFor($secondary->id, $product->id));
    }

    public function test_every_movement_links_to_its_source_transaction(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        $purchase = $this->addStock($user, $supplier, $product, $warehouse, '1000');
        $this->actingAs($user)->post(route('sales.store'), $this->salePayload($customer, $product, $warehouse, '100'));
        $sale = Sale::firstOrFail();
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user);
        $this->post(route('sales.confirm', $sale));

        $purchaseMovement = InventoryMovement::whereNotNull('purchase_item_id')->with('purchaseItem.purchase')->firstOrFail();
        $saleMovement = InventoryMovement::whereNotNull('sale_item_id')->with('saleItem.sale')->firstOrFail();
        $this->assertSame($purchase->id, $purchaseMovement->purchaseItem->purchase->id);
        $this->assertSame($sale->id, $saleMovement->saleItem->sale->id);
        $this->assertNull($purchaseMovement->sale_item_id);
        $this->assertNull($saleMovement->purchase_item_id);
    }
}
