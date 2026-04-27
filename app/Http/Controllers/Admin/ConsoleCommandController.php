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
            Log::warning('Admin console command failed', [
                'command' => $name,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'output' => '',
            ], 500);
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        Log::info('Admin console command run', [
            'command' => $name,
            'user_id' => $request->user()?->id,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $output,
            'duration_ms' => $durationMs,
        ]);
    }
}
