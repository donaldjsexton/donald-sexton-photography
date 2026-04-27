<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanLogsDirectory();
    }

    protected function tearDown(): void
    {
        $this->cleanLogsDirectory();

        parent::tearDown();
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/admin/logs')
            ->assertRedirect('/admin/login');
    }

    public function test_index_renders_empty_state_when_no_logs_exist(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/logs');

        $response->assertOk();
        $response->assertSee('Application Logs');
        $response->assertSee('No log files were found');
    }

    public function test_index_renders_parsed_log_entries(): void
    {
        $admin = User::factory()->create();

        $this->writeLog('laravel.log', <<<'LOG'
            [2026-04-26 10:00:00] testing.INFO: User signed in {"user_id":1}
            [2026-04-26 10:01:00] testing.ERROR: Payment failed
            Stack trace line one
            Stack trace line two
            [2026-04-26 10:02:00] testing.WARNING: Slow query detected
            LOG);

        $response = $this->actingAs($admin)->get('/admin/logs');

        $response->assertOk();
        $response->assertSee('User signed in');
        $response->assertSee('Payment failed');
        $response->assertSee('Slow query detected');
        $response->assertSee('Stack trace line one');
        $response->assertSee('INFO');
        $response->assertSee('ERROR');
        $response->assertSee('WARNING');
    }

    public function test_index_filters_by_level(): void
    {
        $admin = User::factory()->create();

        $this->writeLog('laravel.log', <<<'LOG'
            [2026-04-26 10:00:00] testing.INFO: Routine heartbeat
            [2026-04-26 10:01:00] testing.ERROR: Critical failure
            LOG);

        $response = $this->actingAs($admin)
            ->get('/admin/logs?file=laravel.log&level=ERROR');

        $response->assertOk();
        $response->assertSee('Critical failure');
        $response->assertDontSee('Routine heartbeat');
    }

    public function test_index_filters_by_search_term(): void
    {
        $admin = User::factory()->create();

        $this->writeLog('laravel.log', <<<'LOG'
            [2026-04-26 10:00:00] testing.INFO: Bookings imported
            [2026-04-26 10:01:00] testing.INFO: Inquiries archived
            LOG);

        $response = $this->actingAs($admin)
            ->get('/admin/logs?file=laravel.log&search=archived');

        $response->assertOk();
        $response->assertSee('Inquiries archived');
        $response->assertDontSee('Bookings imported');
    }

    public function test_index_rejects_path_traversal_in_file_param_and_falls_back_to_default(): void
    {
        $admin = User::factory()->create();

        $this->writeLog('laravel.log', "[2026-04-26 10:00:00] testing.INFO: Heartbeat ok\n");

        $response = $this->actingAs($admin)
            ->get('/admin/logs?file=../.env');

        $response->assertOk();
        $response->assertSee('Heartbeat ok');
    }

    private function writeLog(string $name, string $contents): void
    {
        $directory = storage_path('logs');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($directory.DIRECTORY_SEPARATOR.$name, $contents);
    }

    private function cleanLogsDirectory(): void
    {
        $directory = storage_path('logs');

        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.log') ?: [] as $file) {
            @unlink($file);
        }
    }
}
