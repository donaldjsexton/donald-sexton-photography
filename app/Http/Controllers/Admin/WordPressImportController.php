<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressJournalImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WordPressImportController extends Controller
{
    public function __construct(
        private readonly WordPressJournalImporter $importer,
    ) {
    }

    public function index(): RedirectResponse
    {
        return redirect()->to(route('admin.settings.edit', ['tab' => 'imports']).'#wordpress-import');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wxr_file' => ['required', 'file', 'mimetypes:text/xml,application/xml,text/plain', 'max:20480'],
        ]);

        $run = $this->importer->import($validated['wxr_file']);

        return redirect()->to(route('admin.settings.edit', ['tab' => 'imports']).'#wordpress-import')
            ->with('status', "Legacy import completed with status `{$run->status}`.");
    }
}
