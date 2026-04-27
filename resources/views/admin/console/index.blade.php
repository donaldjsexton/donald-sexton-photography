@extends('layouts.admin')

@section('title', 'Artisan Console')
@section('eyebrow', 'Operations')
@section('heading', 'Artisan Console')
@section('subheading', 'Run any allow-listed Artisan command directly from the admin. Destructive and interactive commands (migrate:fresh, db:wipe, tinker, queue:work, etc.) are filtered out for safety.')

@section('content')
    <section class="admin-console">
        <x-admin.section-header
            eyebrow="Commands"
            title="Available Artisan commands"
            description="Commands are grouped by namespace. Expand a row to set arguments and options, then Run."
        />

        <div class="admin-console__layout">
            <aside class="admin-console__nav" aria-label="Command groups">
                <p class="eyebrow">Groups</p>
                <ul class="admin-console__group-list">
                    @foreach ($groups as $group => $commands)
                        <li>
                            <a href="#group-{{ $group }}">
                                <span>{{ $group }}</span>
                                <span class="admin-console__count">{{ count($commands) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </aside>

            <div class="admin-console__main">
                @foreach ($groups as $group => $commands)
                    <section class="admin-console__group" id="group-{{ $group }}">
                        <header class="admin-console__group-head">
                            <h3>{{ $group }}</h3>
                            <span class="meta">{{ count($commands) }} {{ \Illuminate\Support\Str::plural('command', count($commands)) }}</span>
                        </header>

                        <div class="admin-console__commands">
                            @foreach ($commands as $command)
                                <details class="admin-console__command" data-command="{{ $command['name'] }}">
                                    <summary>
                                        <code>{{ $command['name'] }}</code>
                                        <span class="meta">{{ $command['description'] ?: 'No description provided.' }}</span>
                                    </summary>

                                    <form class="admin-console__form" data-command-form>
                                        @csrf
                                        <input type="hidden" name="command" value="{{ $command['name'] }}">

                                        @if (! empty($command['arguments']))
                                            <fieldset class="admin-console__fieldset">
                                                <legend>Arguments</legend>
                                                @foreach ($command['arguments'] as $arg)
                                                    <label class="admin-console__field">
                                                        <span>
                                                            <code>{{ $arg['name'] }}</code>
                                                            @if ($arg['required'])
                                                                <em class="admin-console__required">required</em>
                                                            @endif
                                                            @if ($arg['array'])
                                                                <em class="admin-console__hint">comma-separated</em>
                                                            @endif
                                                        </span>
                                                        @if ($arg['description'])
                                                            <small class="meta">{{ $arg['description'] }}</small>
                                                        @endif
                                                        <input
                                                            type="text"
                                                            name="arguments[{{ $arg['name'] }}]"
                                                            placeholder="{{ $arg['default'] ?? '' }}"
                                                            @if ($arg['required']) required @endif
                                                        >
                                                    </label>
                                                @endforeach
                                            </fieldset>
                                        @endif

                                        @if (! empty($command['options']))
                                            <fieldset class="admin-console__fieldset">
                                                <legend>Options</legend>
                                                @foreach ($command['options'] as $opt)
                                                    @if ($opt['accepts_value'])
                                                        <label class="admin-console__field">
                                                            <span>
                                                                <code>--{{ $opt['name'] }}</code>
                                                                @if ($opt['array'])
                                                                    <em class="admin-console__hint">comma-separated</em>
                                                                @endif
                                                            </span>
                                                            @if ($opt['description'])
                                                                <small class="meta">{{ $opt['description'] }}</small>
                                                            @endif
                                                            <input
                                                                type="text"
                                                                name="options[{{ $opt['name'] }}]"
                                                                placeholder="{{ $opt['default'] ?? '' }}"
                                                            >
                                                        </label>
                                                    @else
                                                        <label class="admin-console__field admin-console__field--toggle">
                                                            <input type="checkbox" name="options[{{ $opt['name'] }}]" value="1">
                                                            <span>
                                                                <code>--{{ $opt['name'] }}</code>
                                                                @if ($opt['description'])
                                                                    <small class="meta">{{ $opt['description'] }}</small>
                                                                @endif
                                                            </span>
                                                        </label>
                                                    @endif
                                                @endforeach
                                            </fieldset>
                                        @endif

                                        <div class="admin-console__actions">
                                            <button type="submit" class="cta-primary admin-console__run">Run command</button>
                                            <span class="admin-console__status meta" data-status></span>
                                        </div>

                                        <pre class="admin-console__output" data-output hidden></pre>
                                    </form>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </section>

    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const runUrl = @json(route('admin.console.run'));

        document.querySelectorAll('[data-command-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const button = form.querySelector('button[type="submit"]');
                const status = form.querySelector('[data-status]');
                const output = form.querySelector('[data-output]');

                const arguments_ = {};
                const options = {};

                form.querySelectorAll('input[name^="arguments["]').forEach((input) => {
                    const match = input.name.match(/arguments\[(.+?)\]/);
                    if (!match) return;
                    if (input.value !== '') arguments_[match[1]] = input.value;
                });

                form.querySelectorAll('input[name^="options["]').forEach((input) => {
                    const match = input.name.match(/options\[(.+?)\]/);
                    if (!match) return;
                    if (input.type === 'checkbox') {
                        if (input.checked) options[match[1]] = '1';
                    } else if (input.value !== '') {
                        options[match[1]] = input.value;
                    }
                });

                button.disabled = true;
                status.textContent = 'Running…';
                output.hidden = true;
                output.textContent = '';
                output.classList.remove('is-success', 'is-error');

                try {
                    const response = await fetch(runUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            command: form.querySelector('input[name="command"]').value,
                            arguments: arguments_,
                            options: options,
                        }),
                    });

                    const payload = await response.json();
                    const text = (payload.output || payload.error || '(no output)').toString();

                    output.hidden = false;
                    output.textContent = text;
                    output.classList.add(payload.ok ? 'is-success' : 'is-error');

                    if (payload.ok) {
                        status.textContent = 'Done · exit ' + (payload.exit_code ?? 0) + ' · ' + (payload.duration_ms ?? 0) + 'ms';
                    } else {
                        status.textContent = 'Failed · exit ' + (payload.exit_code ?? '?');
                    }
                } catch (error) {
                    output.hidden = false;
                    output.textContent = error.message;
                    output.classList.add('is-error');
                    status.textContent = 'Network error';
                } finally {
                    button.disabled = false;
                }
            });
        });
    })();
    </script>
@endsection
