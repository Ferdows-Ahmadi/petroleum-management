<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_cannot_open_master_data_mutation_pages(): void
    {
        $user = User::factory()->create(['role' => UserRole::Accountant]);

        $this->actingAs($user)->get(route('products.create'))->assertForbidden();
        $this->actingAs($user)->get(route('warehouses.create'))->assertForbidden();
        $this->actingAs($user)->get(route('partners.create'))->assertForbidden();
    }

    public function test_purchase_sales_officer_manages_products_and_partners_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]);

        $this->actingAs($user)->get(route('products.create'))->assertOk();
        $this->actingAs($user)->get(route('partners.create'))->assertOk();
        $this->actingAs($user)->get(route('warehouses.create'))->assertForbidden();
    }

    public function test_warehouse_officer_manages_warehouses_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::WarehouseOfficer]);

        $this->actingAs($user)->get(route('warehouses.create'))->assertOk();
        $this->actingAs($user)->get(route('products.create'))->assertForbidden();
        $this->actingAs($user)->get(route('partners.create'))->assertForbidden();
    }
}
