<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Enums\WeighingStatus;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_transaction_workspace_shows_professional_direct_deal_form_without_warehouse(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        Product::factory()->create(['name' => 'Diesel']);
        $this->partner(PartnerRole::Supplier, 'Supplier One');
        $this->partner(PartnerRole::Customer, 'Customer One');

        $this->actingAs($user)->get(route('office.transactions'))
            ->assertOk()
            ->assertSee(__('deals.title'))
            ->assertSee('DIRECT_NET')
            ->assertSee('TWO_STAGE')
            ->assertSee('name="ticket_image"', false)
            ->assertDontSee('name="warehouse_id"', false);
    }

    public function test_purchase_sales_officer_can_save_an_incomplete_draft_but_cannot_submit_weight_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]);

        $this->actingAs($user)->post(route('deals.store'), [
            'deal_date' => '1405/06/08',
            'submit_action' => 'draft',
        ])->assertRedirect();
        $this->assertDatabaseHas('deals', ['status' => DealStatus::Draft->value]);

        $this->actingAs($user)->post(route('deals.store'), [
            'deal_date' => '1405/06/08',
            'weighing_mode' => 'DIRECT_NET',
            'weighing_at' => '1405/06/08 10:00',
            'net_weight_kg' => '1000',
            'submit_action' => 'draft',
        ])->assertForbidden();
    }

    public function test_manager_completes_direct_deal_and_private_ticket_stays_out_of_public_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        $product = Product::factory()->create();
        $supplier = $this->partner(PartnerRole::Supplier, 'Supplier');
        $customer = $this->partner(PartnerRole::Customer, 'Customer');

        $response = $this->actingAs($user)->post(route('deals.store'), [
            'deal_date' => '1405/06/08',
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'source_quantity_kg' => '10000.000',
            'source_rate_per_ton' => '480',
            'customer_id' => $customer->id,
            'destination_rate_per_ton' => '510',
            'weighing_mode' => 'DIRECT_NET',
            'weighing_at' => '1405/06/08 10:00',
            'net_weight_kg' => '10000.000',
            'ticket_image' => UploadedFile::fake()->create('ticket.jpg', 120, 'image/jpeg'),
            'submit_action' => 'complete',
        ]);

        $deal = Deal::query()->firstOrFail();
        $response->assertRedirect(route('deals.show', $deal));
        $weighing = $deal->destinations()->firstOrFail()->weighingRecord;
        $this->assertSame(DealStatus::Completed, $deal->status);
        $this->assertSame(WeighingStatus::Finalized, $weighing->status);
        Storage::disk('local')->assertExists($weighing->ticket_image_path);
        Storage::disk('public')->assertMissing($weighing->ticket_image_path);
        $this->assertStringStartsWith('weighbridge/', $weighing->ticket_image_path);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_unbalanced_completion_is_rejected_before_any_deal_is_created(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);

        $this->actingAs($user)->from(route('deals.index'))->post(route('deals.store'), [
            'deal_date' => '1405/06/08',
            'product_id' => Product::factory()->create()->id,
            'supplier_id' => $this->partner(PartnerRole::Supplier, 'Supplier')->id,
            'source_quantity_kg' => '10000',
            'source_rate_per_ton' => '480',
            'customer_id' => $this->partner(PartnerRole::Customer, 'Customer')->id,
            'final_quantity_kg' => '9999',
            'destination_rate_per_ton' => '510',
            'submit_action' => 'complete',
        ])->assertRedirect(route('deals.index'))->assertSessionHasErrors('final_quantity_kg');

        $this->assertDatabaseCount('deals', 0);
    }

    public function test_accountant_can_view_deals_but_cannot_create_them(): void
    {
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);

        $this->actingAs($accountant)->get(route('deals.index'))->assertOk();
        $this->actingAs($accountant)->post(route('deals.store'), ['deal_date' => '1405/06/08', 'submit_action' => 'draft'])->assertForbidden();
    }

    public function test_transport_officer_can_finish_pending_second_weight_and_deal_atomically(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $transport = User::factory()->create(['role' => UserRole::TransportOfficer]);
        $this->actingAs($manager)->post(route('deals.store'), [
            'deal_date' => '1405/06/08',
            'product_id' => Product::factory()->create()->id,
            'supplier_id' => $this->partner(PartnerRole::Supplier, 'Supplier')->id,
            'source_quantity_kg' => '19355',
            'source_rate_per_ton' => '1200',
            'customer_id' => $this->partner(PartnerRole::Customer, 'Customer')->id,
            'destination_rate_per_ton' => '1310',
            'weighing_mode' => 'TWO_STAGE',
            'weighing_at' => '1405/06/08 10:00',
            'first_weight_kg' => '34475',
            'submit_action' => 'draft',
        ])->assertRedirect();
        $deal = Deal::query()->firstOrFail();

        $this->actingAs($transport)->post(route('deals.weighing.complete', $deal), [
            'second_weight_kg' => '15120',
        ])->assertRedirect(route('deals.show', $deal));

        $this->assertSame(DealStatus::Completed, $deal->refresh()->status);
        $this->assertSame('19355.000', $deal->destinations()->firstOrFail()->final_quantity_kg);
        $this->assertSame('25355.05', $deal->destinations()->firstOrFail()->amount);
    }

    private function partner(PartnerRole $role, string $name): BusinessPartner
    {
        $partner = BusinessPartner::factory()->create(['name' => $name]);
        $partner->roles()->create(['role' => $role]);

        return $partner;
    }
}
