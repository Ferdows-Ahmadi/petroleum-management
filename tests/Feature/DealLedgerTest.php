<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\MoneyMovementStatus;
use App\Enums\MoneyMovementType;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_ledger_shows_stored_rates_totals_and_confirmed_payment_status_only_to_permitted_roles(): void
    {
        $deal = Deal::create(['deal_date' => '2026-09-01', 'status' => DealStatus::Completed]);
        $supplier = BusinessPartner::factory()->create();
        $customer = BusinessPartner::factory()->create();
        $deal->sources()->create([
            'source_type' => 'SUPPLIER', 'supplier_id' => $supplier->id, 'rate_per_ton' => '782.5512',
            'quantity_kg' => '1200', 'amount' => '939.06',
        ]);
        $deal->destinations()->create([
            'destination_type' => 'CUSTOMER', 'customer_id' => $customer->id, 'rate_per_ton' => '832.6543',
            'final_quantity_kg' => '1200', 'amount' => '999.19',
        ]);
        $draft = $deal->moneyMovements()->create([
            'movement_date' => '2026-09-01', 'movement_type' => MoneyMovementType::CustomerReceipt,
            'counterparty_id' => $customer->id, 'status' => MoneyMovementStatus::Draft, 'amount' => '100',
            'amount_usd' => '100', 'currency' => 'USD',
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))
            ->get(route('deals.index'))->assertOk()
            ->assertSee(app(JalaliDate::class)->format($deal->deal_date))
            ->assertSee('1,200')->assertSee('782.5512')->assertSee('832.6543')
            ->assertSee('939.06')->assertSee('999.19')->assertSee(__('finance.settlement_states.UNPAID'));
        $draft->update(['status' => MoneyMovementStatus::Confirmed]);
        $this->get(route('deals.index'))->assertOk()->assertSee(__('finance.settlement_states.PARTIAL'));

        $this->actingAs(User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]))
            ->get(route('deals.index'))->assertOk()->assertSee('782.5512')->assertSee('999.19')
            ->assertDontSee(__('finance.settlement_state'))->assertDontSee(__('finance.settlement_states.PARTIAL'));
        foreach ([UserRole::TransportOfficer, UserRole::WarehouseOfficer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('deals.index'))->assertOk()->assertSee($deal->reference_code)
                ->assertSee('1,200')->assertSee($supplier->name)->assertSee($customer->name)
                ->assertDontSee('782.5512')->assertDontSee('832.6543')->assertDontSee('939.06')
                ->assertDontSee('999.19')->assertDontSee(__('finance.settlement_state'));
        }
    }

    public function test_ledger_filters_keep_their_page_size_sort_order_and_anchor_and_find_generated_references(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]));
        $product = Product::factory()->create();
        $filters = ['product_id' => $product->id, 'status' => DealStatus::Completed->value, 'search' => 'BOOK'];
        $base = ['product_id' => $product->id, 'deal_date' => '2026-09-01', 'status' => DealStatus::Completed];
        for ($i = 1; $i <= 16; $i++) {
            Deal::create($base + ['reference_number' => sprintf('BOOK-%03d', $i)]);
        }
        Deal::create(['status' => DealStatus::Draft, 'reference_number' => 'BOOK-WRONG-STATUS'] + $base);
        Deal::create(['product_id' => Product::factory()->create()->id, 'reference_number' => 'BOOK-WRONG-PRODUCT'] + $base);
        $fallback = Deal::create($base);
        $response = $this->get(route('deals.index', $filters))->assertOk()
            ->assertSeeInOrder(['BOOK-016', 'BOOK-015', 'BOOK-002'])
            ->assertDontSee('BOOK-001')->assertDontSee('BOOK-WRONG')->assertDontSee($fallback->reference_code);
        $page = $response->viewData('deals');
        $this->assertSame(16, $page->total());
        $this->assertSame(15, $page->perPage());
        $response->assertSee($page->url(2));
        $this->assertSame('deal-ledger', parse_url($page->url(2), PHP_URL_FRAGMENT));
        $this->get($page->url(2))->assertOk()->assertSee('BOOK-001')->assertDontSee('BOOK-002');
        $this->get(route('deals.index', ['search' => $fallback->reference_code]))
            ->assertOk()->assertSee(route('deals.show', $fallback))->assertDontSee('BOOK-016');
    }
}
