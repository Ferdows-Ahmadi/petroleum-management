<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => UserRole::WarehouseOfficer]);
    }

    public function test_warehouse_can_be_created_and_viewed_without_editable_stock(): void
    {
        $this->actingAs($this->user)->post(route('warehouses.store'), [
            'name' => 'ذخیره مرکزی', 'location' => 'کابل', 'description' => 'محل نمونه', 'is_active' => '1',
        ])->assertRedirect(route('warehouses.index'));

        $warehouse = Warehouse::firstOrFail();
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'ذخیره مرکزی']);
        $this->assertArrayNotHasKey('stock', $warehouse->getAttributes());
        $this->actingAs($this->user)->get(route('warehouses.show', $warehouse))->assertOk()->assertSee($warehouse->reference_code);
        $this->assertMatchesRegularExpression('/^WH-\d{4,}$/', $warehouse->reference_code);
    }

    public function test_warehouse_validation_and_update_work(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'ذخیره اول']);
        Warehouse::factory()->create(['name' => 'ذخیره دوم']);

        $this->actingAs($this->user)->post(route('warehouses.store'), ['name' => 'ذخیره اول', 'is_active' => '1'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->user)->put(route('warehouses.update', $warehouse), [
            'name' => 'ذخیره ویرایش‌شده', 'location' => 'پلچرخی', 'description' => null, 'is_active' => '0',
        ])->assertRedirect(route('warehouses.show', $warehouse));
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'ذخیره ویرایش‌شده', 'is_active' => false]);
    }

    public function test_warehouse_is_soft_deleted(): void
    {
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->user)->delete(route('warehouses.destroy', $warehouse))->assertRedirect(route('warehouses.index'));
        $this->assertSoftDeleted($warehouse);
    }
}
