<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Enums\WeighingMode;
use App\Enums\WeighingStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\InventoryBalanceService;
use App\Support\NumberFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class SimpleOfficeV21WorkflowTest extends TestCase
{
    use CreatesTradeFixtures, RefreshDatabase;

    public function test_central_number_format_preserves_meaningful_precision_only(): void
    {
        $format = app(NumberFormat::class);

        $this->assertSame('40,645', $format->weight('40645.000'));
        $this->assertSame('19,355.125', $format->weight('19355.125'));
        $this->assertSame('1,310.5', $format->rate('1310.5000'));
        $this->assertSame('25,355.00', $format->money('25355'));
    }

    public function test_purchase_bill_is_private_authorized_and_missing_file_is_safe(): void
    {
        Storage::fake('local');
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct('V21-PU');
        $warehouse = $this->tradeWarehouse('ذخیره V21');

        $this->actingAs($manager)->post(route('purchases.store'), [
            ...$this->purchaseFormPayload($supplier->id, $product->id, $warehouse->id),
            'ticket_image' => $this->fakePng('purchase-bill.png'),
        ])->assertRedirect();

        $purchase = Purchase::firstOrFail();
        $this->assertNotNull($purchase->ticket_image_path);
        $this->assertStringStartsWith('purchase-bills/', $purchase->ticket_image_path);
        Storage::disk('local')->assertExists($purchase->ticket_image_path);
        $this->post(route('logout'));
        $this->get(route('purchases.ticket', $purchase))->assertRedirect(route('login'));
        $this->actingAs($this->tradeUser(UserRole::TransportOfficer))->get(route('purchases.ticket', $purchase))->assertForbidden();
        $this->actingAs($this->tradeUser(UserRole::Accountant))->get(route('purchases.ticket', $purchase))->assertOk();

        Storage::disk('local')->delete($purchase->ticket_image_path);
        $this->actingAs($manager)->get(route('purchases.ticket', $purchase))->assertNotFound()->assertDontSee($purchase->ticket_image_path);
    }

    public function test_draft_purchase_bill_replacement_deletes_old_file(): void
    {
        Storage::fake('local');
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct('V21-REPLACE');
        $warehouse = $this->tradeWarehouse('ذخیره تعویض');
        $payload = $this->purchaseFormPayload($supplier->id, $product->id, $warehouse->id);

        $this->actingAs($manager)->post(route('purchases.store'), [...$payload, 'ticket_image' => $this->fakePng('old.png')]);
        $purchase = Purchase::firstOrFail();
        $oldPath = $purchase->ticket_image_path;
        $this->actingAs($manager)->put(route('purchases.update', $purchase), [...$payload, 'ticket_image' => $this->fakePng('new.png')])->assertRedirect();

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($purchase->refresh()->ticket_image_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('purchase-bills'));
    }

    public function test_confirmed_purchase_bill_cannot_be_replaced_and_new_file_is_cleaned_up(): void
    {
        Storage::fake('local');
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $product = $this->tradeProduct('V21-LOCK');
        $warehouse = $this->tradeWarehouse('ذخیره قفل');
        $payload = $this->purchaseFormPayload($supplier->id, $product->id, $warehouse->id);

        $this->actingAs($manager)->post(route('purchases.store'), [...$payload, 'ticket_image' => $this->fakePng('locked.png')]);
        $purchase = Purchase::firstOrFail();
        $path = $purchase->ticket_image_path;
        $this->actingAs($manager)->post(route('purchases.confirm', $purchase))->assertRedirect();
        $this->actingAs($manager)->from(route('purchases.show', $purchase))->put(route('purchases.update', $purchase), [...$payload, 'ticket_image' => $this->fakePng('replacement.png')])->assertSessionHasErrors('status');

        $this->assertSame($path, $purchase->refresh()->ticket_image_path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles('purchase-bills'));
    }

    public function test_direct_net_sale_confirms_without_requested_or_first_second_weights(): void
    {
        Storage::fake('local');
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('V21-DIRECT');
        $warehouse = $this->tradeWarehouse('ذخیره مستقیم');
        $this->addStock($manager, $supplier, $product, $warehouse, '30000');

        $this->actingAs($manager)->post(route('office.transactions.sales.store'), [
            'partner_name' => $customer->name,
            'sale_date' => '1405/05/19',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rate_per_ton' => '1310',
            'weighing_mode' => WeighingMode::DirectNet->value,
            'net_weight_kg' => '19355',
            'ticket_image' => $this->fakePng('direct.png'),
            'submit_action' => 'confirm',
        ])->assertRedirect();

        $sale = Sale::with('items.weighingRecord')->firstOrFail();
        $item = $sale->items->firstOrFail();
        $record = $item->weighingRecord;
        $this->assertSame(TransactionStatus::Confirmed, $sale->status);
        $this->assertNull($item->requested_quantity_kg);
        $this->assertSame(WeighingMode::DirectNet, $record->weighing_mode);
        $this->assertNull($record->first_weight_kg);
        $this->assertNull($record->second_weight_kg);
        $this->assertSame('19355.000', $record->net_weight_kg);
        $this->assertSame('25355.05', $item->amount);
        Storage::disk('local')->assertExists($record->ticket_image_path);
        $this->assertSame('10645.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));

        $saleWorkspace = $this->actingAs($manager)->get(route('office.transactions', ['tab' => 'sale']))->assertOk();
        $this->assertStringNotContainsString('0 kg</td>', $saleWorkspace->getContent());

        $this->actingAs($manager)->get(route('office.accounts', [
            'partner_query' => $customer->name,
            'partner_id' => $customer->id,
        ]))->assertOk()->assertSee('19,355 kg', false)->assertDontSee('@elseif', false);
    }

    public function test_direct_net_rejects_two_stage_fields(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('V21-MODE');
        $warehouse = $this->tradeWarehouse('ذخیره روش');

        $this->actingAs($manager)->post(route('office.transactions.sales.store'), [
            'partner_name' => $customer->name,
            'sale_date' => '1405/05/19',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rate_per_ton' => '1000',
            'weighing_mode' => WeighingMode::DirectNet->value,
            'weighing_at' => '1405/05/19 10:00',
            'net_weight_kg' => '1000',
            'first_weight_kg' => '2000',
            'submit_action' => 'draft',
        ])->assertSessionHasErrors('first_weight_kg');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_two_stage_bill_can_be_added_then_replaced_at_second_weight(): void
    {
        Storage::fake('local');
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('V21-TWO');
        $warehouse = $this->tradeWarehouse('ذخیره دو مرحله');
        $this->addStock($manager, $supplier, $product, $warehouse, '30000');

        $this->actingAs($manager)->post(route('office.transactions.sales.store'), [
            'partner_name' => $customer->name,
            'sale_date' => '1405/05/19',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'requested_quantity_kg' => '20000',
            'rate_per_ton' => '1310',
            'weighing_mode' => WeighingMode::TwoStage->value,
            'weighing_at' => '1405/05/19 10:00',
            'first_weight_kg' => '34475',
            'ticket_image' => $this->fakePng('first.png'),
            'submit_action' => 'draft',
        ])->assertRedirect();

        $sale = Sale::with('items.weighingRecord')->firstOrFail();
        $record = $sale->items->firstOrFail()->weighingRecord;
        $oldPath = $record->ticket_image_path;
        $this->assertSame(WeighingStatus::Draft, $record->status);
        $this->actingAs($manager)->post(route('office.transactions.sales.weighing.complete', $sale), [
            'second_weight_kg' => '15120',
            'ticket_image' => $this->fakePng('second.png'),
        ])->assertRedirect();

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($record->refresh()->ticket_image_path);
        $this->assertSame(WeighingStatus::Finalized, $record->status);
        $this->assertSame(TransactionStatus::Confirmed, $sale->refresh()->status);
        $this->assertCount(1, Storage::disk('local')->allFiles('weighbridge'));
    }

    public function test_failed_confirmed_sale_leaves_no_orphan_bill(): void
    {
        Storage::fake('local');
        $manager = $this->tradeUser(UserRole::GeneralManager);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('V21-ORPHAN');
        $warehouse = $this->tradeWarehouse('ذخیره خالی V21');

        $this->actingAs($manager)->post(route('office.transactions.sales.store'), [
            'partner_name' => $customer->name,
            'sale_date' => '1405/05/19',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rate_per_ton' => '1000',
            'weighing_mode' => WeighingMode::DirectNet->value,
            'weighing_at' => '1405/05/19 10:00',
            'net_weight_kg' => '1000',
            'ticket_image' => $this->fakePng('orphan.png'),
            'submit_action' => 'confirm',
        ])->assertSessionHasErrors('stock');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('weighing_records', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('weighbridge'));
    }

    public function test_v21_ui_cleanup_modes_uploads_filters_and_favicons_render(): void
    {
        $manager = $this->tradeUser(UserRole::GeneralManager);
        Product::factory()->create();
        Warehouse::factory()->create();

        $this->actingAs($manager)->get(route('office.transactions', ['tab' => 'sale']))
            ->assertOk()
            ->assertSee('DIRECT_NET', false)
            ->assertSee('TWO_STAGE', false)
            ->assertSee('name="ticket_image"', false)
            ->assertDontSee(__('office.form_then_history'));
        $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertDontSee(__('dashboard.notice_title'));
        $this->actingAs($manager)->get(route('office.storage'))->assertOk()->assertDontSee(__('inventory.workspace_notice'))->assertSee(__('inventory.all_products'));
        $this->actingAs($manager)->get(route('reports.index'))->assertOk()->assertDontSee(__('reports.open_report'));
        $this->actingAs($manager)->get(route('reports.sales'))->assertOk()->assertSee(__('reports.all_products'))->assertSee(__('reports.all_statuses'));
        $this->post(route('logout'));
        $this->get(route('login'))->assertOk()->assertSee('favicon-32x32.png', false)->assertSee('apple-touch-icon.png', false)->assertDontSee(__('common.search_placeholder'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')));
    }

    /** @return array<string, mixed> */
    private function purchaseFormPayload(int $supplierId, int $productId, int $warehouseId): array
    {
        return [
            'business_partner_id' => $supplierId,
            'purchase_date' => '1405/05/19',
            'currency' => 'USD',
            'items' => [[
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_kg' => '1000',
                'rate_per_ton' => '1000',
            ]],
        ];
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );
    }
}
