<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerDealRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function entry(): array
    {
        return [
            'submit_action' => 'register', 'deal_date' => '1405/07/11', 'product_id' => Product::factory()->create()->id,
            'supplier_name' => 'عرضه‌کنندهٔ نمونه', 'customer_name' => 'خریدار نمونه', 'source_quantity_kg' => '1000',
            'final_quantity_kg' => '1000', 'source_rate_per_ton' => '480', 'destination_rate_per_ton' => '510', 'notes' => 'یادداشت اصلی آزمایش',
        ];
    }

    public function test_completed_deal_accepts_escaped_ordered_notes_once_without_changing_the_original_or_postings(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $this->actingAs($manager)->post(route('deals.store'), $this->entry())->assertSessionHasNoErrors();
        $deal = Deal::sole();
        $this->assertSame(DealStatus::Completed, $deal->status);
        $snapshot = fn () => collect(['deals', 'deal_sources', 'deal_destinations', 'deal_allocations', 'deal_settlements',
            'inventory_movements', 'money_movements', 'financial_transactions'])->mapWithKeys(
                fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]
            )->all();
        $before = $snapshot();
        $note = ['note_body' => 'رسید <b>شماره ۱</b>', 'operation_key' => (string) Str::uuid()];
        $store = route('deals.notes.store', $deal);
        $this->post($store, $note)->assertRedirect(route('deals.show', $deal).'#deal-notes');
        $this->post($store, $note)->assertSessionHas('operation_recovery');
        $this->post($store, array_replace($note, ['note_body' => 'محتوای دیگر']))->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('deal_notes', 1);
        $this->travel(1)->minutes();
        $staff = User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]);
        $this->actingAs($staff)->post($store, ['note_body' => 'یادداشت دوم', 'operation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame([$manager->id, $staff->id], $deal->additionalNotes()->pluck('created_by')->all());
        $this->get(route('deals.show', $deal))->assertOk()
            ->assertSeeInOrder(['یادداشت اصلی آزمایش', $note['note_body'], 'یادداشت دوم'])->assertDontSee('<b>شماره ۱</b>', false);
        $this->get(route('deals.index'))->assertOk()->assertSee(route('deals.show', $deal).'#deal-notes', false);
        $this->assertSame($before, $snapshot());
    }

    public function test_note_validation_and_permissions_apply_even_to_historical_deals(): void
    {
        $deal = Deal::create(['deal_date' => now(), 'status' => DealStatus::Draft]);
        $store = route('deals.notes.store', $deal);
        foreach ([UserRole::Accountant, UserRole::WarehouseOfficer, UserRole::TransportOfficer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post($store, [
                'note_body' => 'test', 'operation_key' => (string) Str::uuid(),
            ])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::SystemAdmin]));
        foreach ([' ', "\u{200C}", str_repeat('ن', 3001)] as $body) {
            $this->post($store, ['note_body' => $body, 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors('note_body');
        }
        $this->post($store, ['note_body' => 'test'])->assertSessionHasErrors('operation_key');
        $this->assertDatabaseEmpty('deal_notes');
        $this->post($store, ['note_body' => 'بررسی سابقه', 'operation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(DealStatus::Draft, $deal->fresh()->status);
    }

    public function test_partial_delivery_requires_a_real_quantity_and_explicit_intent(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::GeneralManager]));
        $entry = $this->entry();
        $this->post(route('deals.store'), $entry + ['partial_delivery' => 1])->assertSessionHasErrors('final_quantity_kg');
        $entry['final_quantity_kg'] = '750';
        $this->post(route('deals.store'), $entry)->assertSessionHasErrors('final_quantity_kg');
        $this->assertDatabaseEmpty('deals');
        $this->post(route('deals.store'), $entry + ['partial_delivery' => 1])->assertSessionHasNoErrors();
        $deal = Deal::sole();
        $this->assertSame(DealStatus::InProgress, $deal->status);
        $this->assertSame('750.000', $deal->destinations()->sole()->final_quantity_kg);
        $this->assertDatabaseEmpty('money_movements');
        $this->assertDatabaseEmpty('inventory_movements');
        $this->post(route('deals.destinations.store', $deal), [
            'destination_type' => 'CUSTOMER', 'customer_id' => $deal->destinations()->sole()->customer_id,
            'final_quantity_kg' => '250', 'rate_per_ton' => '510',
        ])->assertSessionHasNoErrors();
        $this->post(route('deals.complete', $deal))->assertSessionHasNoErrors();
        $this->assertSame(DealStatus::Completed, $deal->fresh()->status);
    }

    public function test_weighing_workspace_preserves_the_sale_details_price_boundary_and_ledger_link(): void
    {
        $sale = Sale::create([
            'business_partner_id' => BusinessPartner::factory()->create()->id, 'sale_date' => now(),
            'currency' => 'USD', 'status' => 'CONFIRMED',
        ]);
        $sale->items()->create([
            'product_id' => Product::factory()->create()->id, 'warehouse_id' => Warehouse::factory()->create()->id,
            'requested_quantity_kg' => '1000', 'final_quantity_kg' => '1000', 'rate_per_ton' => '98765.43', 'amount' => '98765.43',
        ]);
        foreach ([UserRole::WarehouseOfficer, UserRole::TransportOfficer, UserRole::Accountant] as $role) {
            $page = $this->actingAs(User::factory()->create(['role' => $role]))->get(route('office.transactions'))->assertOk()
                ->assertSee(route('deals.index').'#deal-ledger', false);
            if ($role === UserRole::Accountant) {
                $page->assertSee('98,765.43 USD');
            } else {
                $page->assertDontSee('98,765.43 USD')->assertDontSee(__('trades.total_amount'));
            }
            if ($role === UserRole::WarehouseOfficer) {
                $page->assertSee(route('sales.show', $sale), false);
            }
        }
    }

    public function test_explicit_draft_requests_are_rejected_across_entry_endpoints(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::GeneralManager]));
        foreach (['deals.store', 'office.transactions.purchases.store', 'office.transactions.sales.store', 'purchases.store',
            'sales.store', 'weighings.store', 'money-movements.store', 'office.accounts.money.store', 'office.finance.expenses.store',
            'expenses.store', 'capital-transactions.store'] as $route) {
            $response = $this->post(route($route), ['submit_action' => 'draft', 'status' => 'DRAFT']);
            $response->assertRedirect()->assertSessionHasErrors(['submit_action', 'status']);
        }
        $this->post(route('office.finance.expenses.store'), ['confirm_now' => 0])->assertSessionHasErrors('confirm_now');
        foreach (['deals', 'purchases', 'sales', 'weighing_records', 'financial_transactions', 'money_movements', 'capital_transactions'] as $table) {
            $this->assertDatabaseEmpty($table);
        }
    }
}
