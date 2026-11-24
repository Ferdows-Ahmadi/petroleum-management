<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\PartnerRole;
use App\Enums\TransactionStatus;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Services\InventoryBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class SaleWorkflowTest extends TestCase
{
    use CreatesTradeFixtures, RefreshDatabase;

    public function test_complete_legacy_sale_enters_weighing_and_cannot_be_used_as_an_editable_draft(): void
    {
        $user = $this->tradeUser();
        $partner = $this->tradePartner([PartnerRole::Customer, PartnerRole::Supplier]);

        $response = $this->actingAs($user)->post(route('sales.store'), $this->salePayload($partner, $this->tradeProduct(), $this->tradeWarehouse()));

        $sale = Sale::firstOrFail();
        $response->assertRedirect(route('sales.show', $sale));
        $this->assertSame('AWAITING_WEIGHING', $sale->status->value);
        $this->assertNull($sale->confirmed_at);
        $this->assertNull($sale->items()->sole()->final_quantity_kg);
        $this->put(route('sales.update', $sale), $this->salePayload($partner, $this->tradeProduct('ALT'), $this->tradeWarehouse()))
            ->assertSessionHasErrors('status');
        $this->delete(route('sales.destroy', $sale))->assertSessionHasErrors('status');
        $this->post(route('sales.confirm', $sale))->assertSessionHasErrors('weighing');
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame($partner->id, $sale->business_partner_id);
    }

    public function test_sale_can_be_dispatched_before_the_planned_weight_is_known(): void
    {
        $user = $this->tradeUser();
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $payload = $this->salePayload($customer, $this->tradeProduct(), $this->tradeWarehouse());
        unset($payload['items'][0]['requested_quantity_kg']);

        $this->actingAs($user)->post(route('sales.store'), $payload)->assertSessionHasNoErrors();
        $sale = Sale::sole();
        $this->assertSame(TransactionStatus::AwaitingWeighing, $sale->status);
        $this->assertNull($sale->items()->sole()->requested_quantity_kg);
        $this->post(route('sales.confirm', $sale))->assertSessionHasErrors('weighing');
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_partner_without_customer_role_is_rejected(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);

        $this->actingAs($user)->post(route('sales.store'), $this->salePayload($supplier, $this->tradeProduct(), $this->tradeWarehouse()))
            ->assertSessionHasErrors('business_partner_id');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_petroleum_sale_rejects_non_canonical_trade_currency(): void
    {
        $user = $this->tradeUser();
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $payload = $this->salePayload($customer, $this->tradeProduct(), $this->tradeWarehouse());
        $payload['currency'] = 'AFN';

        $this->actingAs($user)->post(route('sales.store'), $payload)->assertSessionHasErrors('currency');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_requested_sale_amount_overflow_is_rejected_before_database_write(): void
    {
        $user = $this->tradeUser();
        $customer = $this->tradePartner([PartnerRole::Customer]);

        $this->actingAs($user)->post(route('sales.store'), $this->salePayload(
            $customer,
            $this->tradeProduct(),
            $this->tradeWarehouse(),
            '999999999999999',
            '99999999999999',
        ))->assertSessionHasErrors('items.0.rate_per_ton');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_awaiting_weighing_does_not_affect_inventory(): void
    {
        $user = $this->tradeUser();
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();

        $this->actingAs($user)->post(route('sales.store'), $this->salePayload($customer, $product, $warehouse));

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame('0.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_confirming_sale_creates_sale_out_and_decreases_stock(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        $this->addStock($user, $supplier, $product, $warehouse, '2000');
        $this->actingAs($user)->post(route('sales.store'), $this->salePayload($customer, $product, $warehouse, '750'));
        $sale = Sale::firstOrFail();
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user);

        $this->post(route('sales.confirm', $sale))->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $movement = InventoryMovement::whereNotNull('sale_item_id')->firstOrFail();
        $this->assertSame(TransactionStatus::Confirmed, $sale->status);
        $this->assertSame(InventoryMovementType::SaleOut, $movement->movement_type);
        $this->assertSame($sale->items()->firstOrFail()->id, $movement->sale_item_id);
        $this->assertSame('1250.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_sale_confirmation_fails_when_stock_is_insufficient(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        $this->addStock($user, $supplier, $product, $warehouse, '100');
        $this->actingAs($user)->post(route('sales.store'), $this->salePayload($customer, $product, $warehouse, '101'));
        $sale = Sale::firstOrFail();
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user);

        $this->post(route('sales.confirm', $sale))->assertSessionHasErrors('stock');

        $this->assertSame(TransactionStatus::PendingApproval, $sale->refresh()->status);
        $this->assertDatabaseMissing('inventory_movements', ['sale_item_id' => $sale->items()->firstOrFail()->id]);
        $this->assertSame('100.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_failed_multi_item_confirmation_creates_no_partial_sale_movements(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $warehouse = $this->tradeWarehouse();
        $firstProduct = $this->tradeProduct('ONE');
        $secondProduct = $this->tradeProduct('TWO');
        $this->addStock($user, $supplier, $firstProduct, $warehouse, '500');
        $this->addStock($user, $supplier, $secondProduct, $warehouse, '10');
        $payload = $this->salePayload($customer, $firstProduct, $warehouse, '100');
        $payload['items'][] = [
            'product_id' => $secondProduct->id,
            'warehouse_id' => $warehouse->id,
            'requested_quantity_kg' => '20',
            'rate_per_ton' => '1000',
        ];
        $this->actingAs($user)->post(route('sales.store'), $payload);
        $sale = Sale::firstOrFail();
        $this->finalizeSaleItemWeighing($sale->items[0], $user);
        $this->assertSame(TransactionStatus::AwaitingWeighing, $sale->refresh()->status);
        $this->post(route('sales.confirm', $sale))->assertSessionHasErrors('weighing');
        $this->finalizeSaleItemWeighing($sale->items[1], $user);
        $this->assertSame(TransactionStatus::PendingApproval, $sale->refresh()->status);

        $this->post(route('sales.confirm', $sale))->assertSessionHasErrors('stock');

        $this->assertSame(TransactionStatus::PendingApproval, $sale->refresh()->status);
        $this->assertSame(0, InventoryMovement::whereNotNull('sale_item_id')->count());
        $this->assertSame('500.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $firstProduct->id));
        $this->assertSame('10.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $secondProduct->id));
    }

    public function test_verified_business_amount_and_stock_change_are_exact(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('DSL');
        $warehouse = $this->tradeWarehouse();
        $this->addStock($user, $supplier, $product, $warehouse, '30000');
        $this->actingAs($user)->post(route('sales.store'), $this->salePayload($customer, $product, $warehouse, '20000', '1310'));
        $sale = Sale::firstOrFail();
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user, '34475', '15120');

        $this->post(route('sales.confirm', $sale));

        $this->assertSame('25355.05', $sale->items()->firstOrFail()->amount);
        $this->assertSame('19355.000', InventoryMovement::whereNotNull('sale_item_id')->firstOrFail()->quantity_kg);
        $this->assertSame('10645.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_repeated_sale_confirmation_is_idempotent(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        $this->addStock($user, $supplier, $product, $warehouse, '1000');
        $this->actingAs($user)->post(route('sales.store'), $this->salePayload($customer, $product, $warehouse, '400'));
        $sale = Sale::firstOrFail();
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user);

        $this->post(route('sales.confirm', $sale));
        $this->post(route('sales.confirm', $sale));

        $this->assertSame(1, InventoryMovement::whereNotNull('sale_item_id')->count());
        $this->assertSame('600.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }
}
