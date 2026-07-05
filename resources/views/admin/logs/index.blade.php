@extends('layouts.admin')

@section('title', 'Application Logs')
@section('eyebrow', 'Operations')
@section('heading', 'Application Logs')
@section('subheading', 'Tail recent entries from storage/logs. Filter by level or search the message and context.')

@section('content')
    @php
        $levelTone = [
            'EMERGENCY' => 'failed',
            'ALERT' => 'failed',
            'CRITICAL' => 'failed',
            'ERROR' => 'failed',
            'WARNING' => 'running',
            'NOTICE' => 'follow-up',
            'INFO' => 'completed',
            'DEBUG' => 'archived',
        ];
    @endphp

    <section class="admin-card">
        <x-admin.section-header
            eyebrow="Browse"
            title="Log files"
            description="The most recent 512 KB of the selected file are parsed. Older entries stay on disk untouched."
        />

        @if ($files->isEmpty())
            <p class="meta">No log files were found in <code>storage/logs</code>. Logs will appear here as soon as the application writes one.</p>
        @else
            <form method="GET" action="{{ route('admin.logs.index') }}" class="admin-search-form">
                <label>
                    Log file
                    <select name="file">
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
                </label>

                <label>
                    Level
                    <select name="level">
                        <option value="">All levels</option>
                        @foreach ($levels as $option)
                            <option value="{{ $option }}" @selected($level === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Search
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Match message or context"
                    >
                </label>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Apply</button>

                @if ($level !== '' || $search !== '' || ($activeFile && request('file') && request('file') !== $activeFile['name']))
                    <a class="cta-secondary" href="{{ route('admin.logs.index') }}">Reset</a>
                @endif
            </form>
        @endif
    </section>

    @if ($activeFile)
        <section class="admin-stat-grid">
            <article class="admin-card admin-card--metric">
                <p class="eyebrow">File</p>
                <p class="admin-stat" style="font-size: 1.1rem; word-break: break-all;">{{ $activeFile['name'] }}</p>
                <p class="meta">{{ number_format($activeFile['size'] / 1024, 1) }} KB on disk</p>
            </article>

            <article class="admin-card admin-card--metric">
                <p class="eyebrow">Last Write</p>
                <p class="admin-stat">{{ $activeFile['modified_at']->diffForHumans() }}</p>
                <p class="meta">{{ $activeFile['modified_at']->format('M j, Y g:i A') }}</p>
            </article>

            <article class="admin-card admin-card--metric">
                <p class="eyebrow">Parsed Entries</p>
                <p class="admin-stat">{{ number_format($totalEntries) }}</p>
                <p class="meta">
                    @if ($truncated)
                        Truncated to last 512&nbsp;KB
                    @else
                        Full file parsed
                    @endif
                </p>
            </article>

            <article class="admin-card admin-card--metric">
                <p class="eyebrow">Showing</p>
                <p class="admin-stat">{{ number_format($filteredEntries) }}</p>
                <p class="meta">
                    @if ($level !== '' || $search !== '')
                        After filters
                    @else
                        All parsed entries
                    @endif
                </p>
            </article>
        </section>

        <section class="admin-card">
            <x-admin.section-header
                eyebrow="Recent Activity"
                title="Latest entries first"
                description="Each entry shows the timestamp, level, and message. Expand the context for the JSON payload or stack trace when present."
            >
                @if (! empty($entries))
                    <div>
                        <button type="button" class="admin-copy-btn" data-copy-all-entries>Copy all shown</button>
                    </div>
                @endif
            </x-admin.section-header>

            @if (empty($entries))
                <p class="meta">
                    @if ($totalEntries === 0)
                        This file is empty or doesn't contain any parseable Laravel log entries.
                    @else
                        No entries match the current filters. Try clearing them or picking a different log file.
                    @endif
                </p>
            @else
                <ol class="admin-import-run-list" style="list-style: none; padding: 0;">
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

                        <li class="admin-import-run" data-log-entry data-log-line="{{ $logLine }}">
                            <div class="admin-import-run__header">
                                <div class="admin-import-run__copy">
                                    <p class="eyebrow">
                                        {{ $entry['timestamp']?->format('M j, Y g:i:s A') ?: 'Unknown time' }}
                                        @if ($entry['environment'])
                                            · {{ $entry['environment'] }}
                                        @endif
                                    </p>
                                    <h3 class="admin-import-run__title" style="word-break: break-word;">
                                        {{ $entry['message'] !== '' ? $entry['message'] : '(empty message)' }}
                                    </h3>
                                </div>

                                <div class="admin-log-entry__actions">
                                    <x-admin.badge :tone="$levelTone[$entry['level']] ?? 'archived'">
                                        {{ $entry['level'] }}
                                    </x-admin.badge>

                                    <button type="button" class="admin-copy-btn" data-copy-entry aria-label="Copy log entry">
                                        Copy
                                    </button>
                                </div>
                            </div>

                            @if ($entry['context'])
                                <details class="admin-details">
                                    <summary>Context</summary>
                                    <pre class="admin-pre admin-pre--wrap" data-log-context>{{ $entry['context'] }}</pre>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

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
