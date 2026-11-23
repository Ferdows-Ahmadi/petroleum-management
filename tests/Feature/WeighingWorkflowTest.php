<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Enums\WeighingStatus;
use App\Models\BusinessPartner;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WeighingRecord;
use App\Services\InventoryBalanceService;
use App\Services\TradeDraftService;
use App\Services\WeighingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class WeighingWorkflowTest extends TestCase
{
    use CreatesTradeFixtures, RefreshDatabase;

    public function test_guest_is_redirected_from_weighing_list(): void
    {
        $this->get(route('weighings.index'))->assertRedirect(route('login'));
    }

    public function test_every_operational_role_can_view_weighing_list(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->actingAs($this->tradeUser($role))->get(route('weighings.index'))->assertOk();
        }
    }

    public function test_transport_officer_can_create_draft_weighing(): void
    {
        [$sale, $creator] = $this->draftSale();
        $transport = $this->tradeUser(UserRole::TransportOfficer);

        $response = $this->actingAs($transport)->post(route('weighings.store'), $this->weighingPayload($sale->items()->firstOrFail()));

        $record = WeighingRecord::firstOrFail();
        $response->assertRedirect(route('weighings.show', $record));
        $this->assertSame(WeighingStatus::Draft, $record->status);
        $this->assertSame($transport->id, $record->created_by);
        $this->assertNotSame($creator->id, $record->created_by);
    }

    public function test_purchase_sales_officer_cannot_create_weighing(): void
    {
        [$sale, $user] = $this->draftSale();

        $this->actingAs($user)->post(route('weighings.store'), $this->weighingPayload($sale->items()->firstOrFail()))->assertForbidden();
        $this->assertDatabaseCount('weighing_records', 0);
    }

    public function test_accountant_cannot_modify_weighing(): void
    {
        [$sale, $creator] = $this->draftSale();
        $record = app(WeighingService::class)->create($this->weighingPayload($sale->items()->firstOrFail()), $creator->id);
        $accountant = $this->tradeUser(UserRole::Accountant);

        $this->actingAs($accountant)->put(route('weighings.update', $record), $this->weighingPayload($record->saleItem))->assertForbidden();
    }

    public function test_sale_item_is_required_when_creating_weighing(): void
    {
        $transport = $this->tradeUser(UserRole::TransportOfficer);
        $payload = $this->weighingPayload(null);
        unset($payload['sale_item_id']);

        $this->actingAs($transport)->post(route('weighings.store'), $payload)->assertSessionHasErrors('sale_item_id');
    }

    public function test_first_weight_must_be_greater_than_second_weight(): void
    {
        [$sale] = $this->draftSale();
        $transport = $this->tradeUser(UserRole::TransportOfficer);
        $payload = $this->weighingPayload($sale->items()->firstOrFail());
        $payload['first_weight_kg'] = '15120';
        $payload['second_weight_kg'] = '15120';

        $this->actingAs($transport)->post(route('weighings.store'), $payload)->assertSessionHasErrors('first_weight_kg');
        $this->assertDatabaseCount('weighing_records', 0);
    }

    public function test_weight_precision_is_limited_to_three_decimals(): void
    {
        [$sale] = $this->draftSale();
        $transport = $this->tradeUser(UserRole::TransportOfficer);
        $payload = $this->weighingPayload($sale->items()->firstOrFail());
        $payload['first_weight_kg'] = '34475.1234';

        $this->actingAs($transport)->post(route('weighings.store'), $payload)->assertSessionHasErrors('first_weight_kg');
    }

    public function test_net_weight_is_calculated_server_side_and_submitted_net_is_ignored(): void
    {
        [$sale] = $this->draftSale();
        $transport = $this->tradeUser(UserRole::TransportOfficer);
        $payload = $this->weighingPayload($sale->items()->firstOrFail());
        $payload['net_weight_kg'] = '999999';

        $this->actingAs($transport)->post(route('weighings.store'), $payload);

        $this->assertSame('19355.000', WeighingRecord::firstOrFail()->net_weight_kg);
    }

    public function test_only_one_weighing_is_allowed_for_each_sale_item(): void
    {
        [$sale, $creator] = $this->draftSale();
        $item = $sale->items()->firstOrFail();
        $service = app(WeighingService::class);
        $service->create($this->weighingPayload($item), $creator->id);

        $this->expectException(ValidationException::class);
        $service->create($this->weighingPayload($item), $creator->id);
    }

    public function test_draft_weighing_does_not_set_final_quantity_amount_or_inventory(): void
    {
        [$sale, $creator] = $this->draftSale();
        $item = $sale->items()->firstOrFail();
        app(WeighingService::class)->create($this->weighingPayload($item), $creator->id);

        $item->refresh();
        $this->assertNull($item->final_quantity_kg);
        $this->assertSame('0.00', $item->amount);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_updating_draft_recalculates_net_weight(): void
    {
        [$sale, $creator] = $this->draftSale();
        $service = app(WeighingService::class);
        $record = $service->create($this->weighingPayload($sale->items()->firstOrFail()), $creator->id);
        $payload = $this->weighingPayload($record->saleItem);
        unset($payload['sale_item_id']);
        $payload['first_weight_kg'] = '35000';
        $payload['second_weight_kg'] = '15000';

        $service->update($record, $payload);

        $this->assertSame('20000.000', $record->refresh()->net_weight_kg);
    }

    public function test_finalizing_weighing_sets_exact_final_quantity_and_amount(): void
    {
        [$sale, $creator] = $this->draftSale('20000', '1310');
        $record = app(WeighingService::class)->create($this->weighingPayload($sale->items()->firstOrFail()), $creator->id);

        $changed = app(WeighingService::class)->finalize($record, $creator->id);

        $item = $sale->items()->firstOrFail();
        $this->assertTrue($changed);
        $this->assertSame(WeighingStatus::Finalized, $record->refresh()->status);
        $this->assertSame('19355.000', $item->refresh()->final_quantity_kg);
        $this->assertSame('25355.05', $item->amount);
        $this->assertNotNull($record->finalized_at);
    }

    public function test_final_weighed_amount_overflow_is_rejected_without_finalizing(): void
    {
        [$sale, $creator] = $this->draftSale('1', '99999999999999');
        $record = app(WeighingService::class)->create([
            ...$this->weighingPayload($sale->items()->firstOrFail()),
            'first_weight_kg' => '999999999999999',
            'second_weight_kg' => '1',
        ], $creator->id);

        try {
            app(WeighingService::class)->finalize($record, $creator->id);
            $this->fail('Expected final amount overflow validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('first_weight_kg', $exception->errors());
        }

        $this->assertSame(WeighingStatus::Draft, $record->refresh()->status);
        $this->assertNull($sale->items()->firstOrFail()->refresh()->final_quantity_kg);
    }

    public function test_anonymized_exact_workflow_preserves_request_and_changes_inventory_only_on_confirmation(): void
    {
        [$sale, $user, $product, $warehouse, $supplier] = $this->draftSale('20000', '1310');
        $this->addStock($user, $supplier, $product, $warehouse, '30000');
        $item = $sale->items()->firstOrFail();
        $service = app(WeighingService::class);
        $record = $service->create($this->weighingPayload($item), $user->id);

        $this->assertNull($item->refresh()->final_quantity_kg);
        $this->assertSame('30000.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));

        $service->finalize($record, $user->id);

        $item->refresh();
        $this->assertSame('20000.000', $item->requested_quantity_kg);
        $this->assertSame('19355.000', $item->final_quantity_kg);
        $this->assertSame('25355.05', $item->amount);
        $this->assertSame('30000.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));

        $this->actingAs($user)->post(route('sales.confirm', $sale));

        $this->assertSame('19355.000', InventoryMovement::whereNotNull('sale_item_id')->firstOrFail()->quantity_kg);
        $this->assertSame('10645.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_finalized_weighing_cannot_be_updated(): void
    {
        [$sale, $creator] = $this->draftSale();
        $record = $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $creator, '34475', '15120');
        $payload = $this->weighingPayload($record->saleItem);
        unset($payload['sale_item_id']);

        $this->expectException(ValidationException::class);
        app(WeighingService::class)->update($record, $payload);
    }

    public function test_repeated_finalization_is_idempotent(): void
    {
        [$sale, $creator] = $this->draftSale();
        $record = $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $creator, '34475', '15120');
        $originalTime = $record->finalized_at?->toDateTimeString();

        $this->assertFalse(app(WeighingService::class)->finalize($record, $creator->id));
        $this->assertSame($originalTime, $record->refresh()->finalized_at?->toDateTimeString());
    }

    public function test_sale_confirmation_is_blocked_without_a_weighing(): void
    {
        [$sale, $user] = $this->draftSale();

        $this->actingAs($user)->post(route('sales.confirm', $sale))->assertSessionHasErrors('weighing');
        $this->assertSame(TransactionStatus::Draft, $sale->refresh()->status);
    }

    public function test_sale_confirmation_is_blocked_while_weighing_is_draft(): void
    {
        [$sale, $user] = $this->draftSale();
        app(WeighingService::class)->create($this->weighingPayload($sale->items()->firstOrFail()), $user->id);

        $this->actingAs($user)->post(route('sales.confirm', $sale))->assertSessionHasErrors('weighing');
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_confirmed_sale_uses_finalized_net_for_sale_out_and_balance(): void
    {
        [$sale, $user, $product, $warehouse, $supplier] = $this->draftSale('20000');
        $this->addStock($user, $supplier, $product, $warehouse, '30000');
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user, '34475', '15120');

        $this->actingAs($user)->post(route('sales.confirm', $sale));

        $movement = InventoryMovement::whereNotNull('sale_item_id')->firstOrFail();
        $this->assertSame('19355.000', $movement->quantity_kg);
        $this->assertSame('10645.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_insufficient_stock_after_finalization_creates_no_sale_out(): void
    {
        [$sale, $user, $product, $warehouse, $supplier] = $this->draftSale('20000');
        $this->addStock($user, $supplier, $product, $warehouse, '10000');
        $this->finalizeSaleItemWeighing($sale->items()->firstOrFail(), $user, '34475', '15120');

        $this->actingAs($user)->post(route('sales.confirm', $sale))->assertSessionHasErrors('stock');

        $this->assertSame(TransactionStatus::Draft, $sale->refresh()->status);
        $this->assertSame(0, InventoryMovement::whereNotNull('sale_item_id')->count());
    }

    public function test_sale_cannot_be_edited_after_a_weighing_exists(): void
    {
        [$sale, $user] = $this->draftSale();
        app(WeighingService::class)->create($this->weighingPayload($sale->items()->firstOrFail()), $user->id);

        $this->actingAs($user)->get(route('sales.edit', $sale))->assertRedirect(route('sales.show', $sale));
    }

    public function test_sale_cannot_be_deleted_after_a_weighing_exists(): void
    {
        [$sale, $user] = $this->draftSale();
        app(WeighingService::class)->create($this->weighingPayload($sale->items()->firstOrFail()), $user->id);

        $this->actingAs($user)->delete(route('sales.destroy', $sale))->assertSessionHasErrors('items');
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    }

    public function test_ticket_image_is_stored_on_private_local_disk(): void
    {
        Storage::fake('local');
        [$sale] = $this->draftSale();
        $transport = $this->tradeUser(UserRole::TransportOfficer);
        $payload = $this->weighingPayload($sale->items()->firstOrFail());
        $payload['ticket_image'] = $this->fakePng('ticket.png');

        $this->actingAs($transport)->post(route('weighings.store'), $payload);

        $path = WeighingRecord::firstOrFail()->ticket_image_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('weighbridge/', $path);
    }

    public function test_non_image_ticket_upload_is_rejected(): void
    {
        Storage::fake('local');
        [$sale] = $this->draftSale();
        $transport = $this->tradeUser(UserRole::TransportOfficer);
        $payload = $this->weighingPayload($sale->items()->firstOrFail());
        $payload['ticket_image'] = UploadedFile::fake()->createWithContent('ticket.txt', 'not an image');

        $this->actingAs($transport)->post(route('weighings.store'), $payload)->assertSessionHasErrors('ticket_image');
        $this->assertDatabaseCount('weighing_records', 0);
    }

    public function test_guest_cannot_fetch_private_ticket(): void
    {
        $record = $this->recordWithPrivateTicket();

        $this->get(route('weighings.ticket', $record))->assertRedirect(route('login'));
    }

    public function test_accountant_cannot_fetch_private_ticket(): void
    {
        $record = $this->recordWithPrivateTicket();

        $this->actingAs($this->tradeUser(UserRole::Accountant))->get(route('weighings.ticket', $record))->assertForbidden();
    }

    public function test_authorized_role_can_fetch_private_ticket(): void
    {
        $record = $this->recordWithPrivateTicket();

        $this->actingAs($this->tradeUser(UserRole::TransportOfficer))->get(route('weighings.ticket', $record))->assertOk();
    }

    public function test_missing_private_ticket_file_returns_not_found_without_leaking_path(): void
    {
        $record = $this->recordWithPrivateTicket();
        Storage::disk('local')->delete($record->ticket_image_path);

        $this->actingAs($this->tradeUser(UserRole::TransportOfficer))
            ->get(route('weighings.ticket', $record))
            ->assertNotFound()
            ->assertDontSee($record->ticket_image_path);
    }

    public function test_all_sale_items_require_finalized_weighings_before_confirmation(): void
    {
        [$sale, $user, $product, $warehouse, $supplier, $customer] = $this->draftSale('100');
        $secondProduct = $this->tradeProduct('SECOND');
        $secondItem = $sale->items()->create([
            'product_id' => $secondProduct->id,
            'warehouse_id' => $warehouse->id,
            'requested_quantity_kg' => '50',
            'final_quantity_kg' => null,
            'rate_per_ton' => '1000',
            'amount' => '0',
        ]);
        $this->addStock($user, $supplier, $product, $warehouse, '1000');
        $this->addStock($user, $supplier, $secondProduct, $warehouse, '1000');
        $this->finalizeSaleItemWeighing($sale->items()->whereKeyNot($secondItem->id)->firstOrFail(), $user);

        $this->actingAs($user)->post(route('sales.confirm', $sale))->assertSessionHasErrors('weighing');
        $this->assertSame(0, InventoryMovement::whereNotNull('sale_item_id')->count());
        $this->assertSame($customer->id, $sale->customer->id);
    }

    /** @return array{Sale, User, Product, Warehouse, BusinessPartner, BusinessPartner} */
    private function draftSale(string $requested = '500', string $rate = '1310'): array
    {
        $user = $this->tradeUser();
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct();
        $warehouse = $this->tradeWarehouse();
        $sale = app(TradeDraftService::class)->createSale(
            $this->salePayload($customer, $product, $warehouse, $requested, $rate),
            $user->id,
        );

        return [$sale, $user, $product, $warehouse, $supplier, $customer];
    }

    /** @return array<string, mixed> */
    private function weighingPayload(?SaleItem $item): array
    {
        return [
            'sale_item_id' => $item?->id,
            'ticket_number' => 'TEST-WG-001',
            'station_name' => 'پل باسکول آزمایشی',
            'weighing_at' => '2026-08-08 10:00:00',
            'first_weight_kg' => '34475',
            'second_weight_kg' => '15120',
            'vehicle_number' => 'TEST-VEHICLE',
            'driver_name' => 'راننده آزمایشی',
            'notes' => 'اطلاعات ساختگی آزمون',
        ];
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );
    }

    private function recordWithPrivateTicket(): WeighingRecord
    {
        Storage::fake('local');
        [$sale, $creator] = $this->draftSale();
        Storage::disk('local')->put('weighbridge/private-ticket.png', 'private-ticket-content');
        $payload = $this->weighingPayload($sale->items()->firstOrFail());
        $payload['ticket_image_path'] = 'weighbridge/private-ticket.png';

        return app(WeighingService::class)->create($payload, $creator->id);
    }
}
