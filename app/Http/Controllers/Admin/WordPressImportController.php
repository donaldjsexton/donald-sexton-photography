<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use App\Services\WordPress\WordPressJournalImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WordPressImportController extends Controller
{
    public function __construct(
        private readonly WordPressJournalImporter $importer,
    ) {
    }

    public function index(): View
    {
        return view('admin.imports.wordpress', [
            'importRuns' => ImportRun::query()->latest()->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wxr_file' => ['required', 'file', 'mimetypes:text/xml,application/xml,text/plain', 'max:20480'],
        ]);

        $run = $this->importer->import($validated['wxr_file']);

        return redirect()
            ->route('admin.imports.wordpress.index')
            ->with('status', "Legacy import completed with status `{$run->status}`.");
    }
}
