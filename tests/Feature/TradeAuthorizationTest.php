<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class TradeAuthorizationTest extends TestCase
{
    use CreatesTradeFixtures, RefreshDatabase;

    public function test_accountant_can_view_trade_and_inventory_but_cannot_modify_trade(): void
    {
        $user = $this->tradeUser(UserRole::Accountant);

        $this->actingAs($user)->get(route('purchases.index'))->assertOk();
        $this->get(route('sales.index'))->assertOk();
        $this->get(route('inventory.balances'))->assertOk();
        $this->get(route('purchases.create'))->assertForbidden();
        $this->get(route('sales.create'))->assertForbidden();
    }

    public function test_warehouse_officer_can_view_stock_and_movements_but_cannot_modify_trade(): void
    {
        $user = $this->tradeUser(UserRole::WarehouseOfficer);

        $this->actingAs($user)->get(route('inventory.balances'))->assertOk();
        $this->get(route('inventory.movements'))->assertOk();
        $this->get(route('purchases.index'))->assertOk();
        $this->get(route('sales.create'))->assertForbidden();
    }

    public function test_transport_officer_has_no_trade_or_inventory_access(): void
    {
        $user = $this->tradeUser(UserRole::TransportOfficer);

        $this->actingAs($user)->get(route('purchases.index'))->assertForbidden();
        $this->get(route('sales.index'))->assertForbidden();
        $this->get(route('inventory.balances'))->assertForbidden();
        $this->get(route('inventory.movements'))->assertForbidden();
    }

    public function test_purchase_sales_officer_can_open_purchase_and_sale_forms(): void
    {
        $user = $this->tradeUser(UserRole::PurchaseSalesOfficer);

        $this->actingAs($user)->get(route('purchases.create'))->assertOk();
        $this->get(route('sales.create'))->assertOk();
    }
}
