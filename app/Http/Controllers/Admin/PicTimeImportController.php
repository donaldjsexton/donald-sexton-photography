<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use App\Services\PicTime\PicTimeImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PicTimeImportController extends Controller
{
    public function __construct(
        private readonly PicTimeImporter $importer,
    ) {
    }

    public function index(): View
    {
        return view('admin.imports.pictime', [
            'importRuns' => ImportRun::query()
                ->where('source_type', 'pictime')
                ->latest()
                ->paginate(20),
        ]);
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

        return redirect()
            ->route('admin.imports.pictime.index')
            ->with('status', "Pic-Time import completed with status `{$run->status}`.");
    }
}
