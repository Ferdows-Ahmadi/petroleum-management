<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Fixtures\DemoSeeder;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function data(string $email = 'staff@audit.example.test'): array
    {
        return ['name' => 'Audit staff', 'email' => $email, 'role' => UserRole::WarehouseOfficer->value, 'is_active' => true,
            'password' => 'AuditPassword123!', 'password_confirmation' => 'AuditPassword123!'];
    }

    public function test_manager_can_create_hashed_staff_accounts_and_deactivate_them(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $this->actingAs($manager)->get(route('users.create'))->assertOk();
        $this->post(route('users.store'), $this->data())->assertRedirect();
        $staff = User::where('email', 'staff@audit.example.test')->firstOrFail();
        $this->assertTrue(Hash::check('AuditPassword123!', $staff->password));
        $this->put(route('users.update', $staff), ['name' => $staff->name, 'email' => $staff->email, 'role' => 'WAREHOUSE_OFFICER', 'is_active' => false])->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertTrue(Hash::check('AuditPassword123!', $staff->fresh()->password));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'AuditPassword123!'])->assertSessionHasErrors('email');
    }

    public function test_six_roles_are_enforced_on_user_admin_routes_and_financial_role_assignment(): void
    {
        foreach (UserRole::cases() as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($actor)->get(route('users.create'));
            if (in_array($role, [UserRole::GeneralManager, UserRole::SystemAdmin], true)) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
                $this->post(route('users.store'), $this->data())->assertForbidden();
            }
        }
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $this->actingAs($admin)->post(route('users.store'), array_replace($this->data(), ['role' => 'GENERAL_MANAGER']))->assertSessionHasErrors('role');
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);
        $this->get(route('users.edit', $accountant))->assertForbidden();
        $this->put(route('users.update', $accountant), $this->data($accountant->email))->assertForbidden();
    }

    public function test_user_cannot_disable_or_promote_their_own_session(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'WAREHOUSE_OFFICER', 'is_active' => true,
        ])->assertSessionHasErrors('role');
        $this->assertSame(UserRole::SystemAdmin, $admin->fresh()->role);
    }

    public function test_demo_seeding_preserves_changed_passwords_and_rejects_production(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $admin->update(['password' => 'ChangedPassword123!']);
        $this->seed(DemoSeeder::class);
        $this->assertTrue(Hash::check('ChangedPassword123!', $admin->fresh()->password));
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        app(DemoSeeder::class)->run();
    }

    public function test_first_manager_can_be_bootstrapped_without_default_credentials_or_demo_data(): void
    {
        $this->artisan('app:create-manager')->expectsQuestion('Manager name', 'Audit manager')
            ->expectsQuestion('Manager email', 'manager@audit.example.test')
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'AuditPassword123!')
            ->expectsQuestion('Confirm password', 'AuditPassword123!')->assertSuccessful();
        $manager = User::firstOrFail();
        $this->assertSame(UserRole::GeneralManager, $manager->role);
        $this->assertTrue(Hash::check('AuditPassword123!', $manager->password));
        $this->assertDatabaseCount('deals', 0);
        $this->artisan('app:create-manager')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_first_manager_can_receive_a_dari_name_without_windows_question_conversion(): void
    {
        $this->artisan('app:create-manager', ['--name' => 'مدیر عمومی'])
            ->expectsQuestion('Manager email', 'fresh@fictional.example.test')
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'TestManagerPassword123!')
            ->expectsQuestion('Confirm password', 'TestManagerPassword123!')->assertSuccessful();
        $this->assertSame('مدیر عمومی', User::firstOrFail()->name);
    }
}
