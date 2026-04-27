<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LogReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function __construct(private readonly LogReader $reader) {}

    public function index(Request $request): View
    {
        $files = $this->reader->files();

        $requestedFile = trim($request->string('file')->toString());
        $level = strtoupper(trim($request->string('level')->toString()));
        $search = trim($request->string('search')->toString());

        if (! in_array($level, LogReader::LEVELS, true)) {
            $level = '';
        }

        $activeFile = $files->firstWhere('name', $requestedFile);

        if ($activeFile === null) {
            $activeFile = $files->first();
        }

        $entries = [];
        $truncated = false;
        $totalEntries = 0;

        if ($activeFile !== null) {
            $path = $this->reader->path($activeFile['name']);

            if ($path !== null) {
                $result = $this->reader->entries($path);
                $entries = $result['entries'];
                $truncated = $result['truncated'];
                $totalEntries = count($entries);

                if ($level !== '') {
                    $entries = array_values(array_filter(
                        $entries,
                        fn (array $entry): bool => $entry['level'] === $level,
                    ));
                }

                if ($search !== '') {
                    $needle = mb_strtolower($search);
                    $entries = array_values(array_filter(
                        $entries,
                        fn (array $entry): bool => str_contains(mb_strtolower($entry['message']), $needle)
                            || ($entry['context'] !== null && str_contains(mb_strtolower($entry['context']), $needle)),
                    ));
                }
            }
        }

        return view('admin.logs.index', [
            'files' => $files,
            'activeFile' => $activeFile,
            'entries' => $entries,
            'levels' => LogReader::LEVELS,
            'level' => $level,
            'search' => $search,
            'truncated' => $truncated,
            'totalEntries' => $totalEntries,
            'filteredEntries' => count($entries),
        ]);
    }
}
