<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use Illuminate\View\View;

class ImportRunController extends Controller
{
    public function index(): View
    {
        $importRuns = ImportRun::query()
            ->latest()
            ->paginate(12);

        return view('admin.imports.index', [
            'importRuns' => $importRuns,
            'importStats' => [
                [
                    'label' => 'Total Runs',
                    'value' => (string) ImportRun::query()->count(),
                    'meta' => 'All tracked imports across WordPress and Pic-Time.',
                ],
                [
                    'label' => 'WordPress',
                    'value' => (string) ImportRun::query()->where('source_type', 'wordpress')->count(),
                    'meta' => 'Legacy blog imports recorded so far.',
                ],
                [
                    'label' => 'Pic-Time',
                    'value' => (string) ImportRun::query()->where('source_type', 'pictime')->count(),
                    'meta' => 'Gallery-content imports recorded so far.',
                ],
            ],
        ]);
    }
}
