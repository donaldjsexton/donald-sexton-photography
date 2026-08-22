<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Console\ArtisanCommandRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ConsoleCommandController extends Controller
{
    public function __construct(private readonly ArtisanCommandRegistry $registry) {}

    public function index(): View
    {
        return view('admin.console.index', [
            'groups' => $this->registry->grouped(),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string'],
            'arguments' => ['array'],
            'options' => ['array'],
        ]);

        $name = $validated['command'];

        if (! $this->registry->isAllowed($name)) {
            return response()->json([
                'ok' => false,
                'error' => "Command '{$name}' is not allowed from the admin console.",
            ], 422);
        }

        $missing = $this->registry->missingRequiredArguments($name, $validated['arguments'] ?? []);

        if ($missing !== []) {
            return response()->json([
                'ok' => false,
                'error' => "'{$name}' needs a value for: ".implode(', ', $missing).'.',
            ], 422);
        }

        $params = $this->registry->buildParameters(
            $name,
            $validated['arguments'] ?? [],
            $validated['options'] ?? [],
        );

        @set_time_limit(120);

        $started = microtime(true);

        try {
            $exitCode = Artisan::call($name, $params);
            $output = Artisan::output();
        } catch (\Throwable $e) {
            $message = $this->friendlyError($e, $name);

            Log::error('Admin console command failed', [
                'command' => $name,
                'parameters' => $params,
                'user_id' => $request->user()?->id,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $message,
                'output' => Artisan::output(),
            ], 500);
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $context = [
            'command' => $name,
            'parameters' => $params,
            'user_id' => $request->user()?->id,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
        ];

        if ($exitCode === 0) {
            Log::info('Admin console command run', $context);
        } else {
            Log::warning('Admin console command exited non-zero', $context + [
                'output' => str($output)->limit(2000)->toString(),
            ]);
        }

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $output,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * laravel/prompts surfaces a missing interactive answer as a bare
     * "Required." with no hint of which command or field failed, which is
     * useless in a log. Give the operator something actionable.
     */
    private function friendlyError(\Throwable $e, string $command): string
    {
        if (trim($e->getMessage()) === 'Required.') {
            return "'{$command}' asked for input interactively, which the admin console cannot answer. Supply every argument it needs, or run it over SSH.";
        }

        return $e->getMessage();
    }
}
