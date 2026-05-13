<?php

namespace Tests\Feature;

use App\Mail\PortalInvite;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ClientPortalInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_portal_invite_to_client_without_password(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->create([
            'email' => 'sarah@example.com',
            'password' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.clients.portal-invite', $client))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('status');

        Mail::assertSent(PortalInvite::class, function (PortalInvite $mail) use ($client) {
            return $mail->hasTo('sarah@example.com')
                && $mail->client->is($client)
                && str_contains($mail->setupUrl, '/portal/invite/'.$client->uuid.'/setup');
        });
    }

    public function test_admin_cannot_send_invite_when_client_already_has_password(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $client = Client::factory()->withPortalAccess()->create();

        $this->actingAs($admin)
            ->post(route('admin.clients.portal-invite', $client))
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_signed_setup_link_renders_password_form(): void
    {
        $client = Client::factory()->create(['password' => null]);
        $url = URL::temporarySignedRoute('portal.invite.show', now()->addDays(7), ['client' => $client->uuid]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Set Password');
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $client = Client::factory()->create(['password' => null]);

        $this->get(route('portal.invite.show', ['client' => $client->uuid]))
            ->assertStatus(403);
    }

    public function test_setup_link_redirects_when_client_already_has_password(): void
    {
        $client = Client::factory()->withPortalAccess()->create();
        $url = URL::temporarySignedRoute('portal.invite.show', now()->addDays(7), ['client' => $client->uuid]);

        $this->get($url)
            ->assertRedirect(route('portal.login'));
    }

    public function test_submitting_setup_form_sets_password_and_logs_in(): void
    {
        $client = Client::factory()->create([
            'email' => 'sarah@example.com',
            'password' => null,
            'email_verified_at' => null,
        ]);
        $url = URL::temporarySignedRoute('portal.invite.show', now()->addDays(7), ['client' => $client->uuid]);

        $this->post($url, [
            'password' => 'fresh-pass-1',
            'password_confirmation' => 'fresh-pass-1',
        ])
            ->assertRedirect(route('portal.dashboard'));

        $fresh = $client->fresh();
        $this->assertTrue(Hash::check('fresh-pass-1', $fresh->password));
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNotNull($fresh->last_login_at);
        $this->assertTrue(Auth::guard('client')->check());
        $this->assertSame($client->id, Auth::guard('client')->id());
    }

    public function test_setup_form_requires_password_confirmation(): void
    {
        $client = Client::factory()->create(['password' => null]);
        $url = URL::temporarySignedRoute('portal.invite.show', now()->addDays(7), ['client' => $client->uuid]);

        $this->post($url, [
            'password' => 'fresh-pass-1',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertNull($client->fresh()->password);
    }

    public function test_setup_form_requires_minimum_password_length(): void
    {
        $client = Client::factory()->create(['password' => null]);
        $url = URL::temporarySignedRoute('portal.invite.show', now()->addDays(7), ['client' => $client->uuid]);

        $this->post($url, [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_expired_signed_link_is_rejected(): void
    {
        $client = Client::factory()->create(['password' => null]);
        $url = URL::temporarySignedRoute('portal.invite.show', now()->subMinute(), ['client' => $client->uuid]);

        $this->get($url)->assertStatus(403);
    }
}
