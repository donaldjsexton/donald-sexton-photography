<?php

namespace Tests\Feature\Services\Console;

use App\Services\Console\ArtisanCommandRegistry;
use Tests\TestCase;

class ArtisanCommandRegistryTest extends TestCase
{
    public function test_grouped_excludes_denied_exact_commands(): void
    {
        $registry = new ArtisanCommandRegistry;

        $names = $this->flatten($registry->grouped());

        $this->assertNotContains('migrate:fresh', $names);
        $this->assertNotContains('db:wipe', $names);
        $this->assertNotContains('tinker', $names);
        $this->assertNotContains('serve', $names);
        $this->assertNotContains('queue:work', $names);
    }

    public function test_grouped_excludes_bootstrap_cache_writing_commands(): void
    {
        $registry = new ArtisanCommandRegistry;

        $names = $this->flatten($registry->grouped());

        $this->assertNotContains('optimize', $names);
        $this->assertNotContains('optimize:clear', $names);
        $this->assertNotContains('config:cache', $names);
        $this->assertNotContains('config:clear', $names);
        $this->assertNotContains('route:cache', $names);
        $this->assertNotContains('route:clear', $names);
        $this->assertNotContains('view:cache', $names);
        $this->assertNotContains('view:clear', $names);
        $this->assertNotContains('event:cache', $names);
        $this->assertNotContains('event:clear', $names);

        $this->assertFalse($registry->isAllowed('optimize'));
        $this->assertFalse($registry->isAllowed('config:cache'));
    }

    public function test_grouped_excludes_denied_namespaces(): void
    {
        $registry = new ArtisanCommandRegistry;

        $names = $this->flatten($registry->grouped());

        foreach ($names as $name) {
            $this->assertStringStartsNotWith('make:', $name);
            $this->assertStringStartsNotWith('stub:', $name);
            $this->assertStringStartsNotWith('package:', $name);
        }
    }

    public function test_grouped_includes_safe_project_commands(): void
    {
        $registry = new ArtisanCommandRegistry;

        $names = $this->flatten($registry->grouped());

        $this->assertContains('seo:generate-wedding-stories', $names);
        $this->assertContains('route:list', $names);
        $this->assertContains('cache:clear', $names);
    }

    public function test_is_allowed_matches_grouped_output(): void
    {
        $registry = new ArtisanCommandRegistry;

        $this->assertTrue($registry->isAllowed('route:list'));
        $this->assertFalse($registry->isAllowed('migrate:fresh'));
        $this->assertFalse($registry->isAllowed('make:model'));
    }

    public function test_describe_by_name_returns_metadata(): void
    {
        $registry = new ArtisanCommandRegistry;

        $meta = $registry->describeByName('seo:generate-wedding-stories');

        $this->assertNotNull($meta);
        $this->assertSame('seo:generate-wedding-stories', $meta['name']);
        $this->assertSame('seo', $meta['group']);
        $this->assertIsArray($meta['arguments']);
        $this->assertIsArray($meta['options']);

        $optionNames = array_column($meta['options'], 'name');
        $this->assertContains('all', $optionNames);
        $this->assertContains('dry-run', $optionNames);
    }

    public function test_build_parameters_casts_inputs_correctly(): void
    {
        $registry = new ArtisanCommandRegistry;

        $params = $registry->buildParameters(
            'seo:generate-wedding-stories',
            [],
            [
                'all' => '1',
                'limit' => '5',
                'story' => '1,2,3',
            ],
        );

        $this->assertTrue($params['--all']);
        $this->assertSame('5', $params['--limit']);
        $this->assertSame(['1', '2', '3'], $params['--story']);
    }

    public function test_build_parameters_auto_injects_force_for_migrate(): void
    {
        $registry = new ArtisanCommandRegistry;

        $params = $registry->buildParameters('migrate', [], ['pretend' => '1']);

        $this->assertTrue($params['--force']);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grouped
     * @return list<string>
     */
    private function flatten(array $grouped): array
    {
        $names = [];

        foreach ($grouped as $commands) {
            foreach ($commands as $command) {
                $names[] = $command['name'];
            }
        }

        return $names;
    }
}
