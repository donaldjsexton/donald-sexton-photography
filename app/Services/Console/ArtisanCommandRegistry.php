<?php

namespace App\Services\Console;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class ArtisanCommandRegistry
{
    /**
     * Commands that must never be runnable from the web admin. Either destructive,
     * interactive, daemon, or scaffolding-only.
     *
     * @var list<string>
     */
    private const DENY_EXACT = [
        'down',
        'up',
        'serve',
        'tinker',
        'pail',
        'test',
        'reload',
        'db',
        'migrate:fresh',
        'migrate:install',
        'migrate:reset',
        'migrate:rollback',
        'migrate:refresh',
        'db:wipe',
        'db:seed',
        'key:generate',
        'env:encrypt',
        'env:decrypt',
        'queue:work',
        'queue:listen',
        'queue:flush',
        'queue:restart',
        'schedule:work',
        'model:prune',
        'clear-compiled',
    ];

    /**
     * Namespace prefixes (everything before the first ':') that are blocked wholesale.
     *
     * @var list<string>
     */
    private const DENY_NAMESPACES = [
        'make',
        'install',
        'stub',
        'package',
        'mcp',
        'roster',
        'completion',
        'help',
        'list',
        'inspire',
        'docs',
    ];

    /**
     * Commands that need a `--force` flag when running outside of "local" env.
     * We auto-inject `--force` for these so the admin user doesn't need to know.
     *
     * @var list<string>
     */
    private const AUTO_FORCE = [
        'migrate',
        'storage:link',
        'storage:unlink',
        'cache:forget',
        'queue:retry',
    ];

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->allowed() as $command) {
            $meta = $this->describe($command);
            $groups[$meta['group']][] = $meta;
        }

        foreach ($groups as &$commands) {
            usort($commands, fn ($a, $b) => strcmp($a['name'], $b['name']));
        }

        ksort($groups);

        return $groups;
    }

    public function isAllowed(string $name): bool
    {
        foreach ($this->allowed() as $command) {
            if ($command->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function shouldAutoForce(string $name): bool
    {
        return in_array($name, self::AUTO_FORCE, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function describeByName(string $name): ?array
    {
        foreach ($this->allowed() as $command) {
            if ($command->getName() === $name) {
                return $this->describe($command);
            }
        }

        return null;
    }

    /**
     * @return list<SymfonyCommand>
     */
    private function allowed(): array
    {
        $allowed = [];

        foreach (Artisan::all() as $command) {
            $name = $command->getName();

            if ($name === null || $name === '') {
                continue;
            }

            if (in_array($name, self::DENY_EXACT, true)) {
                continue;
            }

            $namespace = str_contains($name, ':') ? strstr($name, ':', true) : $name;

            if (in_array($namespace, self::DENY_NAMESPACES, true)) {
                continue;
            }

            if ($command->isHidden()) {
                continue;
            }

            $allowed[] = $command;
        }

        return $allowed;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(SymfonyCommand $command): array
    {
        $name = (string) $command->getName();
        $group = str_contains($name, ':') ? strstr($name, ':', true) : 'general';

        $definition = $command->getDefinition();

        $arguments = [];

        foreach ($definition->getArguments() as $argument) {
            if (in_array($argument->getName(), ['command'], true)) {
                continue;
            }

            $arguments[] = [
                'name' => $argument->getName(),
                'description' => $argument->getDescription(),
                'required' => $argument->isRequired(),
                'array' => $argument->isArray(),
                'default' => $this->stringifyDefault($argument->getDefault()),
            ];
        }

        $options = [];

        foreach ($definition->getOptions() as $option) {
            if (in_array($option->getName(), ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'], true)) {
                continue;
            }

            $options[] = [
                'name' => $option->getName(),
                'shortcut' => $option->getShortcut(),
                'description' => $option->getDescription(),
                'accepts_value' => $option->acceptValue(),
                'value_required' => $option->isValueRequired(),
                'array' => $option->isArray(),
                'default' => $this->stringifyDefault($option->getDefault()),
            ];
        }

        return [
            'name' => $name,
            'group' => $group,
            'description' => (string) $command->getDescription(),
            'arguments' => $arguments,
            'options' => $options,
        ];
    }

    private function stringifyDefault(mixed $default): ?string
    {
        if ($default === null || $default === false) {
            return null;
        }

        if (is_array($default)) {
            return implode(',', array_map('strval', $default));
        }

        if (is_bool($default)) {
            return $default ? '1' : null;
        }

        return (string) $default;
    }

    /**
     * Cast incoming string parameter values into the shape Artisan::call expects,
     * honoring InputArgument/InputOption metadata.
     *
     * @param  array<string, mixed>  $rawArguments
     * @param  array<string, mixed>  $rawOptions
     * @return array<string, mixed>
     */
    public function buildParameters(string $name, array $rawArguments, array $rawOptions): array
    {
        $command = null;

        foreach ($this->allowed() as $candidate) {
            if ($candidate->getName() === $name) {
                $command = $candidate;
                break;
            }
        }

        if ($command === null) {
            return [];
        }

        $params = [];
        $definition = $command->getDefinition();

        foreach ($definition->getArguments() as $argument) {
            if ($argument->getName() === 'command') {
                continue;
            }

            $key = $argument->getName();
            $raw = $rawArguments[$key] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $params[$key] = $argument->isArray()
                ? $this->splitArrayValue($raw)
                : (string) $raw;
        }

        foreach ($definition->getOptions() as $option) {
            $key = $option->getName();
            $raw = $rawOptions[$key] ?? null;

            if ($raw === null || $raw === '' || $raw === false) {
                continue;
            }

            if (! $option->acceptValue()) {
                $params['--'.$key] = true;

                continue;
            }

            $params['--'.$key] = $option->isArray()
                ? $this->splitArrayValue($raw)
                : (string) $raw;
        }

        if ($this->shouldAutoForce($name) && $definition->hasOption('force') && ! array_key_exists('--force', $params)) {
            $params['--force'] = true;
        }

        return $params;
    }

    /**
     * @return list<string>
     */
    private function splitArrayValue(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw), fn ($v) => $v !== ''));
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string) $raw)),
            fn ($v) => $v !== '',
        ));
    }
}
