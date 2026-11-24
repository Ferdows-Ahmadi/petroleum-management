<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveUxWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_inline_party_selection_adds_missing_role_without_duplicate_identity(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $party = BusinessPartner::factory()->create(['name' => 'شرکت واحد']);
        $party->roles()->create(['role' => PartnerRole::Customer]);

        $this->actingAs($manager)->postJson(route('inline-parties.store'), [
            'partner_id' => $party->id,
            'role' => PartnerRole::Supplier->value,
        ])->assertOk()->assertJsonPath('id', $party->id)->assertJsonFragment(['name' => 'شرکت واحد']);

        $this->assertDatabaseCount('business_partners', 1);
        $this->assertDatabaseHas('business_partner_roles', ['business_partner_id' => $party->id, 'role' => PartnerRole::Customer->value]);
        $this->assertDatabaseHas('business_partner_roles', ['business_partner_id' => $party->id, 'role' => PartnerRole::Supplier->value]);
    }

    public function test_inline_party_creation_reuses_exact_existing_name_and_can_create_saraf(): void
    {
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);
        $party = BusinessPartner::factory()->create(['name' => 'صراف نمونه']);

        $this->actingAs($accountant)->postJson(route('inline-parties.store'), [
            'name' => '  صراف نمونه  ',
            'role' => PartnerRole::Saraf->value,
        ])->assertOk()->assertJsonPath('id', $party->id);

        $this->assertDatabaseCount('business_partners', 1);
        $this->assertDatabaseHas('business_partner_roles', ['business_partner_id' => $party->id, 'role' => PartnerRole::Saraf->value]);
    }

    public function test_inline_party_role_changes_follow_financial_authorization(): void
    {
        $systemAdmin = User::factory()->create(['role' => UserRole::SystemAdmin]);

        $this->actingAs($systemAdmin)->postJson(route('inline-parties.store'), [
            'name' => 'صراف غیرمجاز',
            'role' => PartnerRole::Saraf->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('business_partners', ['name' => 'صراف غیرمجاز']);
    }

    public function test_completed_deal_resolves_inline_names_to_one_party_model(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $product = Product::factory()->create(['is_active' => true]);
        $supplier = BusinessPartner::factory()->create(['name' => 'عرضه‌کننده چندنقشی']);

        $this->actingAs($manager)->post(route('deals.store'), [
            'deal_date' => '1405/06/09',
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'source_quantity_kg' => '1000',
            'source_rate_per_ton' => '900',
            'customer_name' => 'مشتری ایجادشده در معامله',
            'final_quantity_kg' => '1000',
            'destination_rate_per_ton' => '950',
            'weighing_mode' => 'DIRECT_NET',
            'submit_action' => 'complete',
        ])->assertRedirect();

        $deal = Deal::with(['sources.supplier.roles', 'destinations.customer.roles'])->firstOrFail();
        $this->assertSame($supplier->id, $deal->sources->first()->supplier_id);
        $this->assertTrue($deal->sources->first()->supplier->roles->contains('role', PartnerRole::Supplier));
        $this->assertSame('مشتری ایجادشده در معامله', $deal->destinations->first()->customer->name);
        $this->assertTrue($deal->destinations->first()->customer->roles->contains('role', PartnerRole::Customer));
    }

    public function test_deal_workspace_prefills_date_and_product_and_hides_advanced_fields(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $product = Product::factory()->create(['name' => 'محصول پیش‌فرض', 'is_active' => true]);

        $this->actingAs($manager)->get(route('deals.index'))
            ->assertOk()
            ->assertSee('value="'.app(JalaliDate::class)->format(now()).'"', false)
            ->assertSee('value="'.$product->id.'" selected', false)
            ->assertSee(__('deals.weighing'))
            ->assertSee('<details class="cardx card-pad deal-weighing-card"', false)
            ->assertDontSee('value="draft"', false);
    }

    public function test_accounts_filters_and_consolidated_workspaces_are_visible_without_finance_navigation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $customer = BusinessPartner::factory()->create(['name' => 'مشتری فیلترشده']);
        $customer->roles()->create(['role' => PartnerRole::Customer]);
        $supplier = BusinessPartner::factory()->create(['name' => 'عرضه‌کننده پنهان']);
        $supplier->roles()->create(['role' => PartnerRole::Supplier]);

        $this->actingAs($manager)->get(route('office.accounts', ['role' => PartnerRole::Customer->value]))
            ->assertOk()->assertSee($customer->name)->assertDontSee($supplier->name)->assertSee('صراف‌ها')->assertSee('سرمایه');

        $this->actingAs($manager)->get(route('reports.index'))
            ->assertOk()->assertSee('خرید')->assertSee('فروش')->assertSee('تصفیه')->assertSee('مصارف');

        $this->actingAs($manager)->get(route('office.configuration'))
            ->assertOk()->assertSee('محصولات')->assertSee('مکان‌های ذخیره')->assertSee('تنظیمات مالی');

        $dashboard = $this->actingAs($manager)->get(route('dashboard'))->assertOk();
        $dashboard->assertDontSee(route('office.finance'), false)->assertSee(route('finance.journal'), false);
        $this->actingAs($manager)->get(route('office.finance', ['tab' => 'expenses']))
            ->assertRedirect(route('finance.journal', ['entry' => 'expense']));
    }

    public function test_partner_profile_can_be_updated_in_accounts_and_returns_to_the_same_context(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $customer = BusinessPartner::factory()->create(['name' => 'مشتری قابل ویرایش']);
        $customer->roles()->create(['role' => PartnerRole::Customer]);

        $this->actingAs($manager)->get(route('office.accounts', ['partner_id' => $customer->id]))
            ->assertOk()
            ->assertSee('data-account-edit-form', false)
            ->assertSee('return_to', false);

        $this->actingAs($manager)->put(route('partners.update', $customer), [
            'return_to' => 'accounts',
            'name' => $customer->name,
            'phone' => '0700 555 099',
            'address' => 'کابل',
            'notes' => 'ویرایش در همان صفحه',
            'is_active' => true,
            'roles' => [PartnerRole::Customer->value],
        ])->assertRedirect(route('office.accounts', ['partner_id' => $customer->id]));

        $this->assertDatabaseHas('business_partners', [
            'id' => $customer->id,
            'phone' => '0700 555 099',
        ]);
    }
}
