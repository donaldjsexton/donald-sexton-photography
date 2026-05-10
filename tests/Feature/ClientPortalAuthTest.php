<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $this->get(route('portal.login'))
            ->assertOk()
            ->assertSee('Sign in to your portal');
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $client = Client::factory()->create([
            'email' => 'sarah@example.com',
            'password' => 'secret-pass',
        ]);

        $response = $this->post(route('portal.login.store'), [
            'email' => 'sarah@example.com',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertTrue(Auth::guard('client')->check());
        $this->assertSame($client->id, Auth::guard('client')->id());
        $this->assertNotNull($client->fresh()->last_login_at);
    }

    public function test_login_fails_with_bad_credentials(): void
    {
        Client::factory()->create([
            'email' => 'sarah@example.com',
            'password' => 'secret-pass',
        ]);

        $this->post(route('portal.login.store'), [
            'email' => 'sarah@example.com',
            'password' => 'wrong',
        ])
            ->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('client')->check());
    }

    public function test_login_does_not_authenticate_admin_users(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('admin-pass'),
        ]);

        $this->post(route('portal.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'admin-pass',
        ])
            ->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('client')->check());
    }

    public function test_logout_redirects_to_login(): void
    {
        $client = Client::factory()->create(['password' => 'secret-pass']);

        $this->actingAs($client, 'client')
            ->post(route('portal.logout'))
            ->assertRedirect(route('portal.login'));

        $this->assertFalse(Auth::guard('client')->check());
    }

    public function test_dashboard_redirects_guests_to_portal_login(): void
    {
        $this->get(route('portal.dashboard'))
            ->assertRedirect(route('portal.login'));
    }

    public function test_admin_route_redirects_to_admin_login_not_portal(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();
        $client = Client::factory()->create(['email' => 'sarah@example.com']);

        $this->post(route('portal.password.email'), ['email' => 'sarah@example.com'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($client, ResetPassword::class);
    }

    public function test_reset_password_updates_credentials(): void
    {
        Notification::fake();
        $client = Client::factory()->create([
            'email' => 'sarah@example.com',
            'password' => 'old-pass',
        ]);

        $this->post(route('portal.password.email'), ['email' => 'sarah@example.com']);

        $token = null;
        Notification::assertSentTo($client, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('portal.password.update'), [
            'token' => $token,
            'email' => 'sarah@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('portal.login'));

        $this->assertTrue(Hash::check('brand-new-pass', $client->fresh()->password));
    }
}
