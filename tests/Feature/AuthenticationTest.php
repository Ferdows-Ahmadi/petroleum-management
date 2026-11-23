<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_pages_redirect_guests_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('warehouses.index'))->assertRedirect(route('login'));
        $this->get(route('partners.index'))->assertRedirect(route('login'));
    }

    public function test_active_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['email' => 'manager@example.test', 'password' => 'secret-password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'inactive@example.test', 'password' => 'secret-password', 'is_active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_active_user_can_access_auth_only_and_role_protected_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('purchases.index'))->assertOk();
    }

    public function test_logged_in_user_is_logged_out_after_becoming_inactive(): void
    {
        $user = User::factory()->create(['email' => 'later-inactive@example.test', 'password' => 'secret-password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('dashboard'));
        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_session_is_rejected_from_auth_only_and_role_routes(): void
    {
        $authOnlyUser = User::factory()->create(['is_active' => false]);
        $this->actingAs($authOnlyUser)->get(route('products.index'))->assertRedirect(route('login'));

        $roleUser = User::factory()->create(['is_active' => false]);
        $this->actingAs($roleUser)->get(route('purchases.index'))->assertRedirect(route('login'));
    }

    public function test_failed_login_attempts_are_rate_limited(): void
    {
        $email = 'rate-limited@example.test';
        $key = Str::transliterate(Str::lower($email).'|127.0.0.1');
        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), ['email' => $email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
    }
}
