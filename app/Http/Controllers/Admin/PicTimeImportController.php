<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PicTime\PicTimeImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PicTimeImportController extends Controller
{
    public function __construct(
        private readonly PicTimeImporter $importer,
    ) {
    }

    public function index(): RedirectResponse
    {
        return redirect()->to(route('admin.settings.edit', ['tab' => 'imports']).'#pictime-import');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sources' => ['required', 'string'],
            'target' => ['required', Rule::in(['auto', 'journal', 'weddings'])],
        ]);

        $sources = collect(preg_split('/\r\n|\r|\n/', $validated['sources']) ?: [])
            ->map(fn ($source) => trim((string) $source))
            ->filter()
            ->values()
            ->all();

        $run = $this->importer->importSources($sources, $validated['target']);

        return redirect()->to(route('admin.settings.edit', ['tab' => 'imports']).'#pictime-import')
            ->with('status', "Pic-Time import completed with status `{$run->status}`.");
    }
}
