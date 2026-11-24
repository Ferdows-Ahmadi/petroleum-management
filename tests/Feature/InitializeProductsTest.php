<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitializeProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initialization_makes_both_fuels_available_in_the_dropdown_and_is_repeatable(): void
    {
        $this->artisan('app:initialize-products')->assertSuccessful();
        $initial = Product::orderBy('id')->get();
        $this->assertSame(['دیزل', 'پترول'], $initial->pluck('name')->all());
        $this->assertSame(['DSL', 'PTR'], $initial->pluck('code')->all());
        $this->assertTrue($initial->every(fn ($p) => $p->is_active && $p->unit === 'kg'));
        $this->artisan('app:initialize-products')->assertSuccessful();
        $this->assertSame($initial->toArray(), Product::orderBy('id')->get()->toArray());
        $page = $this->actingAs(User::factory()->create(['role' => UserRole::GeneralManager]))->get(route('deals.index'))->assertOk();
        $select = HTMLDocument::createFromString($page->getContent(), LIBXML_NOERROR)->querySelector('#new-deal select[name="product_id"]');
        foreach ($initial as $fuel) {
            $this->assertSame($fuel->name, $select->querySelector("option[value='{$fuel->id}']")->textContent);
        }
        $this->assertSame((string) Product::orderBy('name')->firstOrFail()->id, $select->querySelector('option[selected]')->getAttribute('value'));
        foreach (['inventory_movements', 'money_movements', 'financial_accounts', 'deals'] as $table) {
            $this->assertDatabaseEmpty($table);
        }
    }

    public function test_upgrading_reuses_equivalent_active_names_without_touching_codes_or_lpg(): void
    {
        $diesel = Product::factory()->create(['name' => 'ديزل', 'code' => 'ORIGINAL', 'notes' => 'Original catalog']);
        $lpg = Product::factory()->create(['name' => 'LPG', 'code' => 'LPG']);
        $diesel->refresh();
        $lpg->refresh();
        $this->artisan('app:initialize-products')->assertSuccessful();
        $this->assertDatabaseCount('products', 3);
        $this->assertSame($diesel->getRawOriginal(), $diesel->fresh()->getRawOriginal());
        $this->assertSame($lpg->getRawOriginal(), $lpg->fresh()->getRawOriginal());
        $this->assertDatabaseHas('products', ['code' => 'PTR', 'is_active' => true]);
    }

    public function test_disabled_archived_and_conflicting_matches_fail_atomically(): void
    {
        $petrol = Product::factory()->create(['name' => 'Gasoline', 'is_active' => false]);
        $this->artisan('app:initialize-products')->assertFailed();
        $this->assertDatabaseCount('products', 1);
        $this->assertFalse($petrol->fresh()->is_active);
        $petrol->delete();
        $this->artisan('app:initialize-products')->assertFailed();
        $this->assertDatabaseCount('products', 1);
        $petrol->restore();
        $petrol->update(['is_active' => true, 'code' => 'DSL']);
        $this->artisan('app:initialize-products')->assertFailed();
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('DSL', $petrol->fresh()->code);
    }
}
