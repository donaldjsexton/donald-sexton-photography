@extends('layouts.admin-v2')

@section('title', 'Application Logs')
@section('eyebrow', 'Operations')
@section('heading', 'Application Logs')
@section('subheading', 'Tail recent entries from storage/logs. Filter by level or search the message and context.')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $meta = 'text-sm text-gray-500 dark:text-gray-400';
        $eyebrow = 'text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400';
    @endphp
    @php
        $levelTone = [
            'EMERGENCY' => 'red',
            'ALERT' => 'red',
            'CRITICAL' => 'red',
            'ERROR' => 'red',
            'WARNING' => 'amber',
            'NOTICE' => 'amber',
            'INFO' => 'green',
            'DEBUG' => 'gray',
        ];
    @endphp

    <x-ui.card class="mb-6 space-y-4">
        <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Browse</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Log files</h2>
<p class="{{ $meta }}">The most recent 512 KB of the selected file are parsed. Older entries stay on disk untouched.</p>
</div>
</div>

        @if ($files->isEmpty())
            <p class="{{ $meta }}">No log files were found in <code>storage/logs</code>. Logs will appear here as soon as the application writes one.</p>
        @else
            <form method="GET" action="{{ route('admin.logs.index') }}" class="max-w-none! space-y-4">
                <x-ui.field label="Log file">
                    <select class="{{ $input }}" name="file">
                        @foreach ($files as $file)
                            <option
                                value="{{ $file['name'] }}"
                                @selected($activeFile && $activeFile['name'] === $file['name'])
                            >
                                {{ $file['name'] }}
                                ({{ number_format($file['size'] / 1024, 1) }} KB ·
                                {{ $file['modified_at']->diffForHumans() }})
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Level">
                    <select class="{{ $input }}" name="level">
                        <option value="">All levels</option>
                        @foreach ($levels as $option)
                            <option value="{{ $option }}" @selected($level === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Search">
                    <input class="{{ $input }}"
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Match message or context"
                    >
                </x-ui.field>

                <x-ui.button type="submit">Apply</x-ui.button>

                @if ($level !== '' || $search !== '' || ($activeFile && request('file') && request('file') !== $activeFile['name']))
                    <x-ui.button variant="secondary" :href="route('admin.logs.index')">Reset</x-ui.button>
                @endif
            </form>
        @endif
    </x-ui.card>

    @if ($activeFile)
        <section class="mb-6 [display:grid] grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.card>
                <p class="{{ $eyebrow }}">File</p>
                <p class="break-all text-lg font-semibold text-gray-900 dark:text-white">{{ $activeFile['name'] }}</p>
                <p class="{{ $meta }}">{{ number_format($activeFile['size'] / 1024, 1) }} KB on disk</p>
            </x-ui.card>

            <x-ui.card>
                <p class="{{ $eyebrow }}">Last Write</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $activeFile['modified_at']->diffForHumans() }}</p>
                <p class="{{ $meta }}">{{ $activeFile['modified_at']->format('M j, Y g:i A') }}</p>
            </x-ui.card>

            <x-ui.card>
                <p class="{{ $eyebrow }}">Parsed Entries</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalEntries) }}</p>
                <p class="{{ $meta }}">
                    @if ($truncated)
                        Truncated to last 512&nbsp;KB
                    @else
                        Full file parsed
                    @endif
                </p>
            </x-ui.card>

            <x-ui.card>
                <p class="{{ $eyebrow }}">Showing</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($filteredEntries) }}</p>
                <p class="{{ $meta }}">
                    @if ($level !== '' || $search !== '')
                        After filters
                    @else
                        All parsed entries
                    @endif
                </p>
            </x-ui.card>
        </section>

        <x-ui.card class="mb-6 space-y-4">
            <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Recent Activity</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Latest entries first</h2>
<p class="{{ $meta }}">Each entry shows the timestamp, level, and message. Expand the context for the JSON payload or stack trace when present.</p>
</div>
@if (! empty($entries))
                    <div>
                        <x-ui.button variant="secondary" type="button" class="admin-copy-btn" data-copy-all-entries>Copy all shown</x-ui.button>
                    </div>
                @endif
</div>

            @if (empty($entries))
                <p class="{{ $meta }}">
                    @if ($totalEntries === 0)
                        This file is empty or doesn't contain any parseable Laravel log entries.
                    @else
                        No entries match the current filters. Try clearing them or picking a different log file.
                    @endif
                </p>
            @else
                <ol class="admin-import-run-list m-0 list-none space-y-3 p-0">
                    @foreach ($entries as $entry)
                        @php
                            $logLine = sprintf(
                                '[%s] %s%s: %s',
                                $entry['timestamp']?->format('Y-m-d H:i:s') ?? 'unknown time',
                                $entry['environment'] ? $entry['environment'].'.' : '',
                                $entry['level'],
                                $entry['message'],
                            );
                        @endphp

                        <li class="admin-import-run rounded-xl border border-gray-200 p-4 dark:border-gray-700" data-log-entry data-log-line="{{ $logLine }}">
                            <div class="admin-import-run__header flex items-start justify-between gap-3">
                                <div class="admin-import-run__copy">
                                    <p class="{{ $eyebrow }}">
                                        {{ $entry['timestamp']?->format('M j, Y g:i:s A') ?: 'Unknown time' }}
                                        @if ($entry['environment'])
                                            · {{ $entry['environment'] }}
                                        @endif
                                    </p>
                                    <h3 class="admin-import-run__title break-words text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $entry['message'] !== '' ? $entry['message'] : '(empty message)' }}
                                    </h3>
                                </div>

                                <div class="admin-log-entry__actions flex shrink-0 items-center gap-2">
                                    <x-ui.badge :tone="$levelTone[$entry['level']] ?? 'gray'">
                                        {{ $entry['level'] }}
                                    </x-ui.badge>

                                    <button type="button" class="admin-copy-btn rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" data-copy-entry aria-label="Copy log entry">
                                        Copy
                                    </button>
                                </div>
                            </div>

                            @if ($entry['context'])
                                <details class="admin-details mt-3 text-sm">
                                    <summary>Context</summary>
                                    <pre class="admin-pre admin-pre--wrap mt-2 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-50 p-3 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200" data-log-context>{{ $entry['context'] }}</pre>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-ui.card>

        @if (! empty($entries))
            <script>
            (function () {
                function entryText(entry) {
                    let text = entry.dataset.logLine || '';
                    const context = entry.querySelector('[data-log-context]');

                    if (context && context.textContent.trim() !== '') {
                        text += '\n' + context.textContent;
                    }

                    return text;
                }

                async function copyText(text) {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                        return;
                    }

                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    textarea.remove();
                }

                function flash(button, label) {
                    if (button.dataset.flashing) return;
                    button.dataset.flashing = '1';

                    const original = button.textContent;
                    button.textContent = label;
                    button.classList.add('is-copied');

                    setTimeout(() => {
                        button.textContent = original;
                        button.classList.remove('is-copied');
                        delete button.dataset.flashing;
                    }, 1800);
                }

                document.querySelectorAll('[data-copy-entry]').forEach((button) => {
                    button.addEventListener('click', async () => {
                        const entry = button.closest('[data-log-entry]');
                        if (!entry) return;

                        try {
                            await copyText(entryText(entry));
                            flash(button, 'Copied');
                        } catch {
                            flash(button, 'Failed');
                        }
                    });
                });

                const copyAll = document.querySelector('[data-copy-all-entries]');

                if (copyAll) {
                    copyAll.addEventListener('click', async () => {
                        const text = Array.from(document.querySelectorAll('[data-log-entry]'))
                            .map(entryText)
                            .join('\n\n');

                        try {
                            await copyText(text);
                            flash(copyAll, 'Copied');
                        } catch {
                            flash(copyAll, 'Copy failed');
                        }
                    });
                }
            })();
            </script>
        @endif
    @endif
@endsection
