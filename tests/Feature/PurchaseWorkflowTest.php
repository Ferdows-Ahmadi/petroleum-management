<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\PartnerRole;
use App\Enums\TransactionStatus;
use App\Models\InventoryMovement;
use App\Models\Purchase;
use App\Services\InventoryBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
{
    use CreatesTradeFixtures, RefreshDatabase;

    public function test_complete_legacy_purchase_enters_approval_and_cannot_be_used_as_an_editable_draft(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();

        $response = $this->actingAs($user)->post(route('purchases.store'), $this->purchasePayload($supplier, $product, $warehouse));

        $purchase = Purchase::firstOrFail();
        $response->assertRedirect(route('purchases.show', $purchase));
        $this->assertSame('PENDING_APPROVAL', $purchase->status->value);
        $this->assertNull($purchase->confirmed_at);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->put(route('purchases.update', $purchase), $this->purchasePayload($supplier, $product, $warehouse, '7'))->assertSessionHasErrors('status');
        $this->delete(route('purchases.destroy', $purchase))->assertSessionHasErrors('status');
        $this->assertSame('1000.000', $purchase->items()->sole()->quantity_kg);
        $this->get(route('purchases.show', $purchase))->assertSee(route('purchases.confirm', $purchase), false)->assertDontSee(route('purchases.edit', $purchase), false);
    }

    public function test_partner_without_supplier_role_is_rejected(): void
    {
        $user = $this->tradeUser();
        $customer = $this->tradePartner([PartnerRole::Customer]);

        $this->actingAs($user)->post(route('purchases.store'), $this->purchasePayload($customer, $this->tradeProduct(), $this->tradeWarehouse()))
            ->assertSessionHasErrors('business_partner_id');

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_petroleum_purchase_rejects_non_canonical_trade_currency(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $payload = $this->purchasePayload($supplier, $this->tradeProduct(), $this->tradeWarehouse());
        $payload['currency'] = 'AFN';

        $this->actingAs($user)->post(route('purchases.store'), $payload)->assertSessionHasErrors('currency');
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_purchase_amount_is_calculated_on_the_server(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);

        $this->actingAs($user)->post(route('purchases.store'), $this->purchasePayload($supplier, $this->tradeProduct(), $this->tradeWarehouse(), '19355', '1310'));

        $this->assertSame('25355.05', Purchase::firstOrFail()->items()->firstOrFail()->amount);
    }

    public function test_purchase_amount_overflow_is_rejected_before_database_write(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);

        $this->actingAs($user)->post(route('purchases.store'), $this->purchasePayload(
            $supplier,
            $this->tradeProduct(),
            $this->tradeWarehouse(),
            '999999999999999',
            '99999999999999',
        ))->assertSessionHasErrors('items.0.rate_per_ton');

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_purchase_pending_approval_does_not_affect_inventory(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();

        $this->actingAs($user)->post(route('purchases.store'), $this->purchasePayload($supplier, $product, $warehouse));

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame('0.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_confirming_purchase_creates_purchase_in_and_increases_correct_stock(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        $this->actingAs($user)->post(route('purchases.store'), $this->purchasePayload($supplier, $product, $warehouse, '19355'));
        $purchase = Purchase::firstOrFail();

        $this->actingAs($user)->post(route('purchases.confirm', $purchase))->assertRedirect(route('purchases.show', $purchase));

        $purchase->refresh();
        $movement = InventoryMovement::firstOrFail();
        $this->assertSame(TransactionStatus::Confirmed, $purchase->status);
        $this->assertSame(InventoryMovementType::PurchaseIn, $movement->movement_type);
        $this->assertSame($purchase->items()->firstOrFail()->id, $movement->purchase_item_id);
        $this->assertSame('19355.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_repeated_purchase_confirmation_is_idempotent(): void
    {
        $user = $this->tradeUser();
        $this->actingAs($user);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $this->post(route('purchases.store'), $this->purchasePayload($supplier, $this->tradeProduct(), $this->tradeWarehouse()));
        $record = Purchase::firstOrFail();

        $this->post(route('purchases.confirm', $record));
        $this->post(route('purchases.confirm', $record));

        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_confirmed_purchase_cannot_be_edited_deleted_or_cancelled(): void
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        $this->actingAs($user);
        $this->post(route('purchases.store'), $this->purchasePayload($supplier, $product, $warehouse));
        $purchase = Purchase::firstOrFail();
        $this->post(route('purchases.confirm', $purchase));

        $this->put(route('purchases.update', $purchase), $this->purchasePayload($supplier, $product, $warehouse, '2000'))->assertSessionHasErrors('status');
        $this->delete(route('purchases.destroy', $purchase))->assertSessionHasErrors('status');
        $this->post(route('purchases.cancel', $purchase))->assertSessionHasErrors('status');
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'status' => TransactionStatus::Confirmed->value]);
        $this->assertDatabaseCount('inventory_movements', 1);
    }
}
