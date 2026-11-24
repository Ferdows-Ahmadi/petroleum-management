<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ContactImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InlinePartnerValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_picker_adds_authorized_roles_to_the_imported_general_contact_by_id(): void
    {
        $import = app(ContactImportService::class);
        $name = 'نمونه آزمایشی آفتاب‌گردان';
        $source = 'PARTNER | '.$name;
        $import->apply($source, $import->preview($source, true)['fingerprint'], true);
        $id = BusinessPartner::sole()->id;
        $payload = ['partner_id' => $id, 'role' => 'SARAF'];
        Product::factory()->create(['is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]));
        $this->get(route('deals.index'))->assertOk()->assertSee('id="supplier_id_query_option_'.$id.'"', false);
        $this->postJson(route('inline-parties.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('business_partner_roles', 1);
        foreach (['SUPPLIER', 'CUSTOMER', 'SUPPLIER'] as $role) {
            $this->postJson(route('inline-parties.store'), ['partner_id' => $id, 'role' => $role])
                ->assertOk()->assertJsonPath('id', $id)->assertJsonPath('name', $name);
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]));
        $this->postJson(route('inline-parties.store'), $payload)->assertOk()->assertJsonPath('id', $id);
        $repeat = $import->preview($source, true);
        $this->assertSame(0, $repeat['counts']['create']);
        $this->assertSame('reuse', $repeat['groups'][0]['action']);
        $this->assertSame($id, $repeat['groups'][0]['partner_id']);
        $import->apply($source, $repeat['fingerprint'], true);
        $partner = BusinessPartner::sole();
        $this->assertEqualsCanonicalizing(PartnerRole::cases(), $partner->roles()->get()->pluck('role')->all());
        $this->assertNull($partner->phone);
        $this->assertDatabaseEmpty('deals');
        $this->assertDatabaseEmpty('money_movements');
        $partner->update(['is_active' => false]);
        $this->postJson(route('inline-parties.store'), $payload)->assertUnprocessable();
        $this->assertContains('inactive_or_deleted', $import->preview($source, true)['groups'][0]['reasons']);
    }

    public function test_party_picker_validates_name_and_phone_before_creating_a_contact(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::GeneralManager]));
        $name = str_repeat('ش', 150);
        $phone = str_repeat('8', 30);
        $this->postJson(route('inline-parties.store'), ['role' => 'CUSTOMER', 'name' => $name.'ی', 'phone' => $phone.'0'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);
        $this->assertDatabaseEmpty('business_partners');
        $this->postJson(route('inline-parties.store'), ['role' => 'CUSTOMER', 'name' => $name, 'phone' => $phone])
            ->assertOk()->assertJsonPath('name', $name)->assertJsonPath('phone', $phone);
    }

    public function test_daily_deal_rejects_overlong_inline_names_and_accepts_the_exact_column_limit(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::GeneralManager]));
        $entry = ['deal_date' => '1405/08/10', 'submit_action' => 'register', 'product_id' => Product::factory()->create()->id,
            'source_quantity_kg' => '1000', 'final_quantity_kg' => '1000', 'source_rate_per_ton' => '700', 'destination_rate_per_ton' => '750'];
        $supplier = str_repeat('م', 150);
        $customer = str_repeat('ن', 150);
        $this->post(route('deals.store'), $entry + ['supplier_name' => $supplier.'ی', 'customer_name' => $customer.'ی'])
            ->assertSessionHasErrors(['supplier_name', 'customer_name']);
        $this->assertDatabaseEmpty('business_partners');
        $this->assertDatabaseEmpty('deals');

        $this->post(route('deals.store'), $entry + ['supplier_name' => $supplier, 'customer_name' => $customer])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_partners', ['name' => $supplier]);
        $this->assertDatabaseHas('business_partners', ['name' => $customer]);
    }

    #[TestWith(['office.transactions.purchases.store', 'purchases', 'purchase_date', 'quantity_kg'])]
    #[TestWith(['office.transactions.sales.store', 'sales', 'sale_date', 'requested_quantity_kg'])]
    public function test_office_trade_validates_the_stored_name_and_phone_lengths(string $route, string $table, string $date, string $quantity): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::GeneralManager]));
        $entry = [
            $date => '1405/08/10', $quantity => '1000', 'rate_per_ton' => '700', 'submit_action' => $table === 'sales' ? 'await_weighing' : 'confirm',
            'product_id' => Product::factory()->create()->id, 'warehouse_id' => Warehouse::factory()->create()->id,
        ];
        $name = str_repeat('آ', 150);
        $phone = str_repeat('7', 30);
        $this->post(route($route), $entry + ['partner_name' => $name.'ر', 'partner_phone' => $phone.'0'])
            ->assertSessionHasErrors(['partner_name', 'partner_phone']);
        $this->assertDatabaseEmpty('business_partners');
        $this->assertDatabaseEmpty($table);

        $this->post(route($route), $entry + ['partner_name' => $name, 'partner_phone' => $phone])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_partners', ['name' => $name, 'phone' => $phone]);
        $this->assertDatabaseCount($table, 1);
    }
}
