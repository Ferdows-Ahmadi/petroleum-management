<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessPartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]);
    }

    public function test_partner_can_have_customer_role(): void
    {
        $partner = $this->createPartner([PartnerRole::Customer->value]);
        $this->assertDatabaseHas('business_partner_roles', ['business_partner_id' => $partner->id, 'role' => PartnerRole::Customer->value]);
    }

    public function test_partner_can_have_supplier_role(): void
    {
        $partner = $this->createPartner([PartnerRole::Supplier->value]);
        $this->assertDatabaseHas('business_partner_roles', ['business_partner_id' => $partner->id, 'role' => PartnerRole::Supplier->value]);
    }

    public function test_same_partner_can_have_both_roles(): void
    {
        $partner = $this->createPartner([PartnerRole::Customer->value, PartnerRole::Supplier->value]);
        $this->assertDatabaseCount('business_partner_roles', 2);
        $this->actingAs($this->user)->get(route('partners.show', $partner))->assertOk()->assertSee($partner->name);
    }

    public function test_partner_can_have_saraf_and_partner_roles_together(): void
    {
        $partner = $this->createPartner([PartnerRole::Saraf->value, PartnerRole::Partner->value]);

        $this->assertEqualsCanonicalizing(
            [PartnerRole::Saraf->value, PartnerRole::Partner->value],
            $partner->roles->pluck('role.value')->all(),
        );
    }

    public function test_literal_legacy_both_role_is_normalized_without_changing_party_id(): void
    {
        $partner = BusinessPartner::factory()->create();
        DB::table('business_partner_roles')->insert(['business_partner_id' => $partner->id, 'role' => 'BOTH']);

        $migration = require database_path('migrations/2026_08_30_100000_normalize_v3_party_roles.php');
        $migration->up();

        $this->assertDatabaseMissing('business_partner_roles', ['business_partner_id' => $partner->id, 'role' => 'BOTH']);
        $this->assertDatabaseHas('business_partner_roles', ['business_partner_id' => $partner->id, 'role' => PartnerRole::Customer->value]);
        $this->assertDatabaseHas('business_partner_roles', ['business_partner_id' => $partner->id, 'role' => PartnerRole::Supplier->value]);
    }

    public function test_partner_list_can_filter_each_normalized_role(): void
    {
        $saraf = $this->createPartner([PartnerRole::Saraf->value]);
        $customer = $this->createPartner([PartnerRole::Customer->value]);

        $this->actingAs($this->user)->get(route('partners.index', ['role' => PartnerRole::Saraf->value]))
            ->assertOk()
            ->assertSee($saraf->name)
            ->assertSee(PartnerRole::Saraf->label())
            ->assertDontSee($customer->name);
    }

    public function test_partner_requires_at_least_one_valid_role(): void
    {
        $this->actingAs($this->user)->post(route('partners.store'), [
            'name' => 'شرکت نمونه', 'is_active' => '1', 'roles' => [],
        ])->assertSessionHasErrors('roles');
    }

    public function test_partner_roles_can_be_updated_and_partner_soft_deleted(): void
    {
        $partner = $this->createPartner([PartnerRole::Customer->value]);

        $this->actingAs($this->user)->put(route('partners.update', $partner), [
            'name' => 'شرکت ویرایش‌شده', 'phone' => null, 'address' => 'کابل', 'notes' => null,
            'is_active' => '0', 'roles' => [PartnerRole::Supplier->value],
        ])->assertRedirect(route('partners.show', $partner));
        $this->assertDatabaseMissing('business_partner_roles', ['business_partner_id' => $partner->id, 'role' => PartnerRole::Customer->value]);
        $this->assertDatabaseHas('business_partner_roles', ['business_partner_id' => $partner->id, 'role' => PartnerRole::Supplier->value]);

        $this->actingAs($this->user)->delete(route('partners.destroy', $partner))->assertRedirect(route('partners.index'));
        $this->assertSoftDeleted($partner);
    }

    private function createPartner(array $roles): BusinessPartner
    {
        $this->actingAs($this->user)->post(route('partners.store'), [
            'name' => 'شرکت نمونه '.implode('-', $roles), 'phone' => '0700 000 000', 'address' => 'کابل',
            'notes' => null, 'is_active' => '1', 'roles' => $roles,
        ])->assertRedirect();

        return BusinessPartner::latest('id')->firstOrFail();
    }
}
