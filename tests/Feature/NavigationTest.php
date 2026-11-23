<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transport_navigation_contains_only_its_simple_office_areas(): void
    {
        $transport = User::factory()->create(['role' => UserRole::TransportOfficer]);

        $this->actingAs($transport)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('office.transactions'), false)
            ->assertSee(route('reports.index'), false)
            ->assertDontSee(route('office.accounts'), false)
            ->assertDontSee(route('office.storage'), false)
            ->assertDontSee(route('office.finance'), false)
            ->assertDontSee(route('office.configuration'), false)
            ->assertDontSee(route('weighings.index'), false)
            ->assertDontSee(route('products.index'), false);
    }

    public function test_operational_sidebar_hides_internal_and_future_modules(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $response = $this->actingAs($manager)->get(route('dashboard'))->assertOk();

        foreach ([
            route('purchases.index'), route('sales.index'), route('products.index'), route('warehouses.index'),
            route('inventory.movements'), route('weighings.index'), route('payments.index'),
            route('capital-partners.index'), route('capital-transactions.index'),
        ] as $internalUrl) {
            $response->assertDontSee($internalUrl, false);
        }
        $response->assertSee(route('office.configuration'), false);
    }

    public function test_six_role_sidebar_visibility_matches_simple_work_areas(): void
    {
        foreach (UserRole::cases() as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route('dashboard'))->assertOk();
            $response->assertSee(route('office.transactions'), false)->assertSee(route('reports.index'), false);

            if (in_array($role, [UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::Accountant, UserRole::PurchaseSalesOfficer], true)) {
                $response->assertSee(route('office.accounts'), false);
            } else {
                $response->assertDontSee(route('office.accounts'), false);
            }

            if ($role === UserRole::TransportOfficer) {
                $response->assertDontSee(route('office.storage'), false);
            } else {
                $response->assertSee(route('office.storage'), false);
            }

            $response->assertDontSee(route('office.finance'), false);

            if (in_array($role, [UserRole::GeneralManager, UserRole::SystemAdmin, UserRole::Accountant], true)) {
                $response->assertSee(route('finance.journal'), false);
            } else {
                $response->assertDontSee(route('finance.journal'), false);
            }

            if (in_array($role, [UserRole::GeneralManager, UserRole::SystemAdmin], true)) {
                $response->assertSee(route('office.configuration'), false);
            } else {
                $response->assertDontSee(route('office.configuration'), false);
            }
        }
    }
}
