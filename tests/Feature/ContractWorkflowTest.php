<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_contract_with_jalali_dates(): void
    {
        $user = User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]);
        $partner = BusinessPartner::factory()->create();

        $this->actingAs($user)->post(route('contracts.store'), $this->payload($partner))->assertRedirect();

        $contract = Contract::firstOrFail();
        $this->assertSame('2026-08-10', $contract->start_date->toDateString());
        $this->assertSame('2026-09-10', $contract->end_date->toDateString());
        $this->assertSame('25000.00', $contract->agreed_amount);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $payload = $this->payload(BusinessPartner::factory()->create());
        $payload['end_date'] = '1405/05/18';

        $this->actingAs($user)->post(route('contracts.store'), $payload)->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_contract_status_must_be_allowlisted(): void
    {
        $user = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $payload = $this->payload(BusinessPartner::factory()->create());
        $payload['status'] = 'SIGNED';

        $this->actingAs($user)->post(route('contracts.store'), $payload)->assertSessionHasErrors('status');
    }

    public function test_contract_number_is_unique_and_existing_contract_can_keep_its_number(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        $partner = BusinessPartner::factory()->create();
        $this->actingAs($user)->post(route('contracts.store'), $this->payload($partner));
        $contract = Contract::firstOrFail();

        $this->actingAs($user)->put(route('contracts.update', $contract), $this->payload($partner))->assertRedirect(route('contracts.show', $contract));
        $this->actingAs($user)->post(route('contracts.store'), $this->payload($partner))->assertSessionHasErrors('contract_number');
    }

    public function test_new_contract_requires_an_explicit_non_draft_status_and_historical_drafts_stay_editable(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        $partner = BusinessPartner::factory()->create();
        $this->actingAs($user)->get(route('contracts.create'))->assertOk()->assertDontSee('value="DRAFT"', false);
        $payload = array_replace($this->payload($partner), ['status' => 'DRAFT']);
        $this->post(route('contracts.store'), $payload)->assertSessionHasErrors('status');
        $this->post(route('contracts.store'), array_replace($payload, ['status' => '']))->assertSessionHasErrors('status');
        $this->assertDatabaseEmpty('contracts');
        $this->get(route('contracts.create'))->assertOk();
        $old = Contract::create(array_replace($this->modelPayload($partner, $user->id), ['status' => 'DRAFT']));
        $this->get(route('contracts.edit', $old))->assertOk()->assertSee('value="DRAFT" selected', false);
        $this->put(route('contracts.update', $old), $payload)->assertSessionHasNoErrors();
        $this->assertSame(ContractStatus::Draft, $old->fresh()->status);
        $this->put(route('contracts.update', $old), $this->payload($partner))->assertSessionHasNoErrors();
        $this->put(route('contracts.update', $old), $payload)->assertSessionHasErrors('status');
        $this->assertSame(ContractStatus::Active, $old->fresh()->status);
    }

    public function test_accountant_can_view_contract_but_cannot_mutate_it(): void
    {
        $creator = User::factory()->create(['role' => UserRole::GeneralManager]);
        $contract = Contract::create($this->modelPayload(BusinessPartner::factory()->create(), $creator->id));
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);

        $this->actingAs($accountant)->get(route('contracts.index'))->assertOk();
        $this->actingAs($accountant)->get(route('contracts.show', $contract))->assertOk();
        $this->actingAs($accountant)->get(route('contracts.create'))->assertForbidden();
    }

    public function test_contract_is_independent_and_does_not_create_trade_records(): void
    {
        $user = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $this->actingAs($user)->post(route('contracts.store'), $this->payload(BusinessPartner::factory()->create()));

        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('sales', 0);
    }

    /** @return array<string, mixed> */
    private function payload(BusinessPartner $partner): array
    {
        return ['business_partner_id' => $partner->id, 'contract_number' => 'CNT-TEST-001', 'contract_type' => ContractType::Purchase->value, 'start_date' => '1405/05/19', 'end_date' => '1405/06/19', 'agreed_amount' => '25000', 'currency' => 'USD', 'terms' => 'Test contract terms', 'status' => ContractStatus::Active->value];
    }

    /** @return array<string, mixed> */
    private function modelPayload(BusinessPartner $partner, int $userId): array
    {
        $payload = $this->payload($partner);
        $payload['start_date'] = '2026-08-10';
        $payload['end_date'] = '2026-09-10';
        $payload['created_by'] = $userId;

        return $payload;
    }
}
