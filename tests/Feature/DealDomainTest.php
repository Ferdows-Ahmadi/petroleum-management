<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\DealStatus;
use App\Enums\WeighingMode;
use App\Enums\WeighingStatus;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use App\Services\DealService;
use App\Services\PartnerLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\HistoricalDeal;
use Tests\TestCase;

class DealDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_registration_requires_a_supported_action_even_when_all_commercial_data_is_valid(): void
    {
        $user = User::factory()->create();
        $data = [
            'deal_date' => '2026-08-30', 'product_id' => Product::factory()->create()->id,
            'supplier_id' => BusinessPartner::factory()->create()->id, 'source_quantity_kg' => '1000', 'source_rate_per_ton' => '480',
            'customer_id' => BusinessPartner::factory()->create()->id, 'final_quantity_kg' => '1000', 'destination_rate_per_ton' => '510',
        ];
        $service = app(DealService::class);
        foreach ([[], ['submit_action' => 'unexpected'], ['submit_action' => 'draft']] as $action) {
            try {
                $service->create($data + $action, $user->id);
                $this->fail('Unsupported registration action accepted.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('submit_action', $error->errors());
            }
        }
        try {
            $service->create($data + ['submit_action' => 'register', 'partial_delivery' => true], $user->id);
            $this->fail('A fully delivered deal cannot be parked as partial.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('final_quantity_kg', $error->errors());
        }
        $this->assertDatabaseEmpty('deals');
        $this->assertDatabaseEmpty('deal_sources');
        $this->assertDatabaseEmpty('deal_destinations');
        $this->assertDatabaseEmpty('deal_allocations');
        $this->assertDatabaseEmpty('business_partner_roles');

        $historical = HistoricalDeal::draft($data, $user->id);
        try {
            $service->updateDraft($historical, $data + ['submit_action' => 'unexpected', 'notes' => 'Should roll back'], $user->id);
            $this->fail('Unsupported edit action accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('submit_action', $error->errors());
        }
        $this->assertNull($historical->fresh()->notes);
        $this->assertSame(DealStatus::Draft, $historical->fresh()->status);

        $registered = $service->create($data + ['submit_action' => 'register'], $user->id);
        $this->assertSame(DealStatus::Completed, $registered->status);
        $this->assertSame('510.00', $registered->destinations->sole()->amount);
        $this->assertDatabaseCount('deals', 2);
        $this->assertDatabaseEmpty('money_movements');
    }

    public function test_deal_has_one_product_and_normalized_source_and_destination_rows(): void
    {
        $product = Product::factory()->create();
        $supplier = BusinessPartner::factory()->create();
        $customer = BusinessPartner::factory()->create();
        $creator = User::factory()->create();

        $deal = Deal::query()->create([
            'deal_date' => '2026-08-30',
            'product_id' => $product->id,
            'status' => DealStatus::Draft,
            'created_by' => $creator->id,
        ]);
        $deal->sources()->create([
            'supplier_id' => $supplier->id,
            'quantity_kg' => '10000.000',
            'rate_per_ton' => '500.0000',
            'amount' => '5000.00',
            'currency' => Currency::Usd,
        ]);
        $deal->destinations()->create([
            'customer_id' => $customer->id,
            'final_quantity_kg' => '10000.000',
            'rate_per_ton' => '520.0000',
            'amount' => '5200.00',
            'currency' => Currency::Usd,
        ]);

        $this->assertSame($product->id, $deal->product->id);
        $this->assertSame($supplier->id, $deal->sources->first()->supplier->id);
        $this->assertSame($customer->id, $deal->destinations->first()->customer->id);
        $this->assertSame('5000.00', $deal->total_source_amount);
        $this->assertSame('5200.00', $deal->total_destination_amount);
        $this->assertSame('DL-'.str_pad((string) $deal->id, 6, '0', STR_PAD_LEFT), $deal->reference_code);
    }

    public function test_deal_schema_allows_incomplete_draft_without_warehouse(): void
    {
        $deal = Deal::query()->create([
            'deal_date' => '2026-08-30',
            'status' => DealStatus::Draft,
        ]);

        $deal->sources()->create([]);
        $deal->destinations()->create([]);

        $this->assertDatabaseHas('deals', ['id' => $deal->id, 'status' => DealStatus::Draft->value]);
        $this->assertDatabaseCount('warehouses', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_direct_deal_completion_is_atomic_and_updates_party_ledgers_without_fake_transactions(): void
    {
        $product = Product::factory()->create();
        $supplier = BusinessPartner::factory()->create();
        $customer = BusinessPartner::factory()->create();
        $user = User::factory()->create();

        $deal = app(DealService::class)->create([
            'deal_date' => '2026-08-30',
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'source_quantity_kg' => '12500.000',
            'source_rate_per_ton' => '480.0000',
            'customer_id' => $customer->id,
            'final_quantity_kg' => '12500.000',
            'destination_rate_per_ton' => '510.0000',
            'submit_action' => 'complete',
        ], $user->id);

        $this->assertSame(DealStatus::Completed, $deal->status);
        $this->assertSame('6000.00', $deal->sources->first()->amount);
        $this->assertSame('6375.00', $deal->destinations->first()->amount);
        $this->assertSame('-6000.00', app(PartnerLedgerService::class)->balanceFor($supplier));
        $this->assertSame('6375.00', app(PartnerLedgerService::class)->balanceFor($customer));
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_unbalanced_direct_deal_rolls_back_the_whole_creation(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(DealService::class)->create([
                'deal_date' => '2026-08-30',
                'product_id' => Product::factory()->create()->id,
                'supplier_id' => BusinessPartner::factory()->create()->id,
                'source_quantity_kg' => '12000.000',
                'source_rate_per_ton' => '480.0000',
                'customer_id' => BusinessPartner::factory()->create()->id,
                'final_quantity_kg' => '11999.000',
                'destination_rate_per_ton' => '510.0000',
                'submit_action' => 'complete',
            ], User::factory()->create()->id);
        } finally {
            $this->assertDatabaseCount('deals', 0);
        }
    }

    public function test_direct_net_weighing_finalizes_a_deal_destination_without_a_sale(): void
    {
        $user = User::factory()->create();
        $deal = app(DealService::class)->create([
            'deal_date' => '2026-08-30',
            'product_id' => Product::factory()->create()->id,
            'supplier_id' => BusinessPartner::factory()->create()->id,
            'source_quantity_kg' => '10000.000',
            'source_rate_per_ton' => '480.0000',
            'customer_id' => BusinessPartner::factory()->create()->id,
            'destination_rate_per_ton' => '510.0000',
            'weighing_mode' => WeighingMode::DirectNet->value,
            'weighing_at' => '2026-08-30 09:30:00',
            'net_weight_kg' => '10000.000',
            'ticket_number' => 'WB-100',
            'submit_action' => 'complete',
        ], $user->id);

        $weighing = $deal->destinations->first()->weighingRecord;
        $this->assertSame(DealStatus::Completed, $deal->status);
        $this->assertSame(WeighingStatus::Finalized, $weighing->status);
        $this->assertSame('10000.000', $deal->destinations->first()->final_quantity_kg);
        $this->assertNull($weighing->sale_item_id);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_first_two_stage_weight_moves_deal_to_in_progress_without_completion(): void
    {
        $deal = app(DealService::class)->create([
            'deal_date' => '2026-08-30',
            'product_id' => Product::factory()->create()->id,
            'supplier_id' => BusinessPartner::factory()->create()->id,
            'source_quantity_kg' => '10000.000',
            'source_rate_per_ton' => '480.0000',
            'customer_id' => BusinessPartner::factory()->create()->id,
            'destination_rate_per_ton' => '510.0000',
            'weighing_mode' => WeighingMode::TwoStage->value,
            'weighing_at' => '2026-08-30 09:30:00',
            'first_weight_kg' => '28000.000',
            'submit_action' => 'register',
        ], User::factory()->create()->id);

        $this->assertSame(DealStatus::InProgress, $deal->status);
        $this->assertSame(WeighingStatus::Draft, $deal->destinations->first()->weighingRecord->status);
        $this->assertNull($deal->destinations->first()->final_quantity_kg);
    }

    public function test_reference_direct_trade_example_has_exact_purchase_sale_and_preliminary_margin(): void
    {
        $deal = app(DealService::class)->create([
            'deal_date' => '2026-08-30',
            'product_id' => Product::factory()->create(['name' => 'Diesel'])->id,
            'supplier_id' => BusinessPartner::factory()->create(['name' => 'Supplier A'])->id,
            'source_quantity_kg' => '19355.000',
            'source_rate_per_ton' => '1200.0000',
            'customer_id' => BusinessPartner::factory()->create(['name' => 'Customer B'])->id,
            'destination_rate_per_ton' => '1310.0000',
            'weighing_mode' => WeighingMode::TwoStage->value,
            'weighing_at' => '2026-08-30 11:00:00',
            'first_weight_kg' => '34475.000',
            'second_weight_kg' => '15120.000',
            'submit_action' => 'complete',
        ], User::factory()->create()->id);

        $this->assertSame('19355.000', $deal->destinations->first()->final_quantity_kg);
        $this->assertSame('23226.00', $deal->total_source_amount);
        $this->assertSame('25355.05', $deal->total_destination_amount);
        $this->assertSame('2129.05', bcsub($deal->total_destination_amount, $deal->total_source_amount, 2));
        $this->assertDatabaseCount('warehouses', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }
}
