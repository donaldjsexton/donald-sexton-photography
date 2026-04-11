@props([
    'columns' => [],
])

<div class="admin-table-wrap">
    <table {{ $attributes->class('admin-table') }}>
        @if (! empty($columns))
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
