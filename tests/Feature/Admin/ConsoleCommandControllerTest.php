<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsoleCommandControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->get('/admin/console')
            ->assertRedirect('/admin/login');
    }

    public function test_index_renders_grouped_commands_for_admin(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/console');

        $response->assertOk();
        $response->assertSee('Artisan Console');
        $response->assertSee('seo:generate-wedding-stories');
    }

    public function test_run_executes_an_allowed_command(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson('/admin/console/run', [
                'command' => 'config:show',
                'arguments' => ['config' => 'app.name'],
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['ok', 'exit_code', 'output', 'duration_ms']);
        $this->assertSame(0, $response->json('exit_code'));
    }

    public function test_run_rejects_denied_command(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson('/admin/console/run', [
                'command' => 'migrate:fresh',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
    }

    public function test_run_rejects_namespaced_denied_command(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson('/admin/console/run', [
                'command' => 'make:model',
                'arguments' => ['name' => 'Foo'],
            ]);

        $response->assertStatus(422);
    }

    public function test_run_requires_authentication(): void
    {
        $this->postJson('/admin/console/run', ['command' => 'route:list'])
            ->assertStatus(401);
    }
}
