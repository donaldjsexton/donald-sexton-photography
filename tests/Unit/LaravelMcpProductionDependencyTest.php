<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LaravelMcpProductionDependencyTest extends TestCase
{
    public function test_laravel_mcp_is_installed_with_production_dependencies(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('laravel/mcp', $composer['require']);
        $this->assertArrayNotHasKey('laravel/mcp', $composer['require-dev']);

        $production = array_column($lock['packages'], 'name');
        $development = array_column($lock['packages-dev'], 'name');

        $this->assertContains('laravel/mcp', $production);
        $this->assertNotContains('laravel/mcp', $development);
    }
}
