<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => UserRole::SystemAdmin]);
    }

    public function test_product_can_be_created_and_code_is_normalized(): void
    {
        $this->actingAs($this->user)->post(route('products.store'), [
            'name' => 'دیزل ممتاز', 'code' => 'dsl-x', 'unit' => 'kg', 'notes' => null, 'is_active' => '1',
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['name' => 'دیزل ممتاز', 'code' => 'DSL-X', 'unit' => 'kg', 'is_active' => true]);
    }

    public function test_product_creation_validates_required_and_unique_values(): void
    {
        Product::factory()->create(['name' => 'دیزل', 'code' => 'DSL']);

        $this->actingAs($this->user)->post(route('products.store'), [
            'name' => 'دیزل', 'code' => 'DSL', 'unit' => 'litre', 'is_active' => '1',
        ])->assertSessionHasErrors(['name', 'code', 'unit']);
    }

    public function test_product_can_be_updated_and_soft_deleted(): void
    {
        $product = Product::factory()->create(['name' => 'پترول', 'code' => 'PTR']);

        $this->actingAs($this->user)->put(route('products.update', $product), [
            'name' => 'پترول سوپر', 'code' => 'PTR-S', 'unit' => 'kg', 'notes' => 'نمونه', 'is_active' => '0',
        ])->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'پترول سوپر', 'is_active' => false]);

        $this->actingAs($this->user)->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));
        $this->assertSoftDeleted($product);
    }
}
