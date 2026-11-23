<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InlinePartnerValidationTest extends TestCase
{
    use RefreshDatabase;

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
        $draft = ['deal_date' => '1405/08/10', 'submit_action' => 'draft'];
        $supplier = str_repeat('م', 150);
        $customer = str_repeat('ن', 150);
        $this->post(route('deals.store'), $draft + ['supplier_name' => $supplier.'ی', 'customer_name' => $customer.'ی'])
            ->assertSessionHasErrors(['supplier_name', 'customer_name']);
        $this->assertDatabaseEmpty('business_partners');
        $this->assertDatabaseEmpty('deals');

        $this->post(route('deals.store'), $draft + ['supplier_name' => $supplier, 'customer_name' => $customer])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_partners', ['name' => $supplier]);
        $this->assertDatabaseHas('business_partners', ['name' => $customer]);
    }

    #[TestWith(['office.transactions.purchases.store', 'purchases', 'purchase_date', 'quantity_kg'])]
    #[TestWith(['office.transactions.sales.store', 'sales', 'sale_date', 'requested_quantity_kg'])]
    public function test_office_trade_validates_the_stored_name_and_phone_lengths(string $route, string $table, string $date, string $quantity): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::GeneralManager]));
        $draft = [
            $date => '1405/08/10', $quantity => '1000', 'rate_per_ton' => '700', 'submit_action' => 'draft',
            'product_id' => Product::factory()->create()->id, 'warehouse_id' => Warehouse::factory()->create()->id,
        ];
        $name = str_repeat('آ', 150);
        $phone = str_repeat('7', 30);
        $this->post(route($route), $draft + ['partner_name' => $name.'ر', 'partner_phone' => $phone.'0'])
            ->assertSessionHasErrors(['partner_name', 'partner_phone']);
        $this->assertDatabaseEmpty('business_partners');
        $this->assertDatabaseEmpty($table);

        $this->post(route($route), $draft + ['partner_name' => $name, 'partner_phone' => $phone])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_partners', ['name' => $name, 'phone' => $phone]);
        $this->assertDatabaseCount($table, 1);
    }
}
