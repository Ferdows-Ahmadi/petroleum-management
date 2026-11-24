<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DealSettlementService;
use App\Services\InventoryBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class CompanyOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_seeding_never_installs_accounts_or_business_data(): void
    {
        $this->seed();
        foreach (['users', 'products', 'business_partners', 'financial_accounts', 'warehouses', 'deals'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_first_deal_guides_manager_to_only_the_required_product_setup(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $this->actingAs($manager)->get(route('deals.index'))->assertOk()
            ->assertSee('ثبت اولین محصول')->assertSee(route('products.create'), false)
            ->assertDontSee('data-deal-form', false);
        Product::factory()->create();
        $this->get(route('deals.index'))->assertOk()->assertSee('data-deal-form', false);
        $this->assertDatabaseCount('warehouses', 0);
        $this->assertDatabaseCount('financial_accounts', 0);
    }

    public function test_trade_staff_get_clear_setup_guidance_without_an_unauthorized_link(): void
    {
        $staff = User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]);
        $this->actingAs($staff)->get(route('deals.index'))->assertOk()
            ->assertSee('از مدیر بخواهید')->assertDontSee(route('products.create'), false);
    }

    public function test_empty_cash_account_guidance_respects_manager_and_accountant_authority(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $partner = BusinessPartner::factory()->create();
        $partner->roles()->create(['role' => PartnerRole::Customer]);
        $this->actingAs($manager)->get(route('office.accounts', ['partner_id' => $partner->id]))->assertOk()
            ->assertSee('ثبت اولین حساب مالی')->assertSee(route('financial-accounts.create'), false);
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);
        $this->actingAs($accountant)->get(route('office.accounts', ['partner_id' => $partner->id]))->assertOk()
            ->assertSee('از مدیر عمومی بخواهید')->assertDontSee(route('financial-accounts.create'), false);
    }

    public function test_empty_warehouse_screen_explains_when_setup_is_needed(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $this->actingAs($manager)->get(route('office.storage'))->assertOk()
            ->assertSee('ثبت اولین ذخیره')->assertSee('به ذخیره نیاز ندارد')
            ->assertSee(route('warehouses.create'), false);
    }

    public function test_normal_form_can_buy_into_storage_and_sell_with_actual_cost_without_dummy_parties(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $data = [
            'deal_date' => '1405/07/11', 'product_id' => $product->id,
            'supplier_name' => 'عرضه‌کننده ذخیره', 'source_quantity_kg' => '2000',
            'source_rate_per_ton' => '1000', 'destination_type' => 'STORAGE',
            'destination_warehouse_id' => $warehouse->id, 'submit_action' => 'register',
            'partial_delivery' => '1', 'final_quantity_kg' => '1000',
        ];
        $this->actingAs($manager)->post(route('deals.store'), $data)->assertSessionHasNoErrors();
        $purchase = Deal::query()->firstOrFail();
        $sourceId = $purchase->sources()->firstOrFail()->id;
        $destinationId = $purchase->destinations()->firstOrFail()->id;
        $this->get(route('deals.edit', $purchase))->assertOk()->assertSee('ذخیره دریافت');
        $this->put(route('deals.update', $purchase), array_replace(Arr::except($data, ['partial_delivery']), ['submit_action' => 'complete', 'final_quantity_kg' => '2000']))
            ->assertSessionHasNoErrors()->assertRedirect(route('deals.show', $purchase));
        $this->assertDatabaseCount('deals', 1);
        $this->assertDatabaseCount('business_partners', 1);
        $this->assertDatabaseHas('deal_sources', ['id' => $sourceId, 'deal_id' => $purchase->id]);
        $this->assertDatabaseHas('deal_destinations', [
            'id' => $destinationId, 'destination_type' => 'STORAGE', 'warehouse_id' => $warehouse->id,
            'customer_id' => null, 'final_quantity_kg' => '2000.000', 'rate_per_ton' => null,
        ]);
        app(DealSettlementService::class)->confirm($purchase->refresh(), $manager->id);
        $this->assertSame('2000.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
        $this->post(route('deals.store'), [
            'deal_date' => '1405/07/11', 'product_id' => $product->id,
            'source_type' => 'STORAGE', 'source_warehouse_id' => $warehouse->id,
            'source_quantity_kg' => '750', 'supplier_name' => 'Must not be created',
            'source_rate_per_ton' => '999999', 'customer_name' => 'مشتری ذخیره',
            'final_quantity_kg' => '750', 'destination_rate_per_ton' => '1200', 'submit_action' => 'complete',
        ])->assertSessionHasNoErrors();
        $sale = Deal::query()->latest('id')->firstOrFail();
        $this->assertNull($sale->sources()->firstOrFail()->supplier_id);
        $settlement = app(DealSettlementService::class)->confirm($sale, $manager->id);
        $this->assertSame('750.00', $settlement->source_value_usd);
        $this->assertSame('900.00', $settlement->sale_revenue_usd);
        $this->assertSame('150.00', $settlement->final_result_usd);
        $this->assertDatabaseCount('business_partners', 2);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame('1250.000', app(InventoryBalanceService::class)->balanceFor($warehouse->id, $product->id));
    }

    public function test_normal_storage_entry_rejects_inactive_warehouses_and_unsupported_transfers(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create(['is_active' => false]);
        $data = [
            'deal_date' => '1405/07/11', 'product_id' => $product->id,
            'source_type' => 'STORAGE', 'source_warehouse_id' => $warehouse->id,
            'source_quantity_kg' => '750', 'customer_name' => 'مشتری',
            'final_quantity_kg' => '750', 'destination_rate_per_ton' => '1200', 'submit_action' => 'complete',
        ];
        $this->actingAs($manager)->post(route('deals.store'), $data)->assertSessionHasErrors('source_warehouse_id');
        $warehouse->update(['is_active' => true]);
        $this->post(route('deals.store'), $data + ['destination_type' => 'STORAGE', 'destination_warehouse_id' => $warehouse->id])
            ->assertSessionHasErrors('destination_type');
        $this->assertDatabaseCount('deals', 0);
        $this->assertDatabaseCount('business_partners', 0);
    }
}
