<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TradeEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeFixtures;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesTradeFixtures, RefreshDatabase;

    public function test_dashboard_focuses_on_derived_business_position_without_quick_access_grid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['inventory_kg'] === '0.000'
                && $metrics['today_purchases'] === '0.00'
                && $metrics['today_sales'] === '0.00')
            ->assertDontSee(__('dashboard.quick_actions'));
    }

    public function test_transport_dashboard_shows_only_non_financial_sale_activity_without_dead_links(): void
    {
        $creator = $this->tradeUser(UserRole::PurchaseSalesOfficer);
        $transport = $this->tradeUser(UserRole::TransportOfficer);
        $supplier = $this->tradePartner([PartnerRole::Supplier]);
        $customer = $this->tradePartner([PartnerRole::Customer]);
        $product = $this->tradeProduct('DASH-AUTH');
        $warehouse = $this->tradeWarehouse('ذخیره داشبورد');
        $purchase = app(TradeEntryService::class)->createPurchase($this->purchasePayload($supplier, $product, $warehouse), $creator->id);
        $sale = app(TradeEntryService::class)->createSale($this->salePayload($customer, $product, $warehouse), $creator->id);

        $this->actingAs($transport)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertDontSee($supplier->name)
            ->assertDontSee(route('sales.show', $sale), false)
            ->assertDontSee(route('purchases.show', $purchase), false)
            ->assertDontSee(__('finance.amount'));
    }
}
