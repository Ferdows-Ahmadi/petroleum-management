<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Enums\UserRole;
use App\Models\BusinessPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_account_has_its_own_heading_and_ledger_and_a_filtered_return_link(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $partner = BusinessPartner::factory()->create();
        $partner->roles()->create(['role' => PartnerRole::Customer]);
        $filter = ['search' => $partner->name, 'role' => PartnerRole::Customer->value];
        $directory = route('office.accounts', $filter);

        $this->actingAs($manager)->get($directory)->assertOk()
            ->assertSee($partner->name)->assertSee('class="filters"', false)
            ->assertDontSee('id="account-profile-heading"', false);

        $this->get(route('office.accounts', ['partner_id' => $partner->id, ...$filter]))->assertOk()
            ->assertSee('<h1 class="page-title">'.e($partner->name).'</h1>', false)
            ->assertSee($directory)->assertSee($partner->reference_code)
            ->assertSeeInOrder(['account-profile-heading', 'balance-callout', 'account-ledger-heading'], false)
            ->assertDontSee('class="filters"', false);

        $staff = User::factory()->create(['role' => UserRole::PurchaseSalesOfficer]);
        $this->actingAs($staff)->get(route('office.accounts', ['partner_id' => $partner->id]))->assertOk()
            ->assertSee($partner->name)->assertDontSee('account-ledger-heading')->assertDontSee('balance-callout');
    }
}
