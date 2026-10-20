{{--
    Single-series bar chart (Chart.js, bundled by Vite) with the same numbers in a table.
    Params: $id, $title (series name), $labels (array), $values (array), $axis (optional), $max (optional y max), $decimals (optional).
--}}
@php($decimals ??= 0)
<div class="chart-box" role="img" aria-label="{{ $title }}: @foreach ($labels as $i => $label){{ $label }} {{ $values[$i] }}@if (! $loop->last), @endif @endforeach">
    <canvas id="{{ $id }}"></canvas>
</div>
<details class="mt-2 small">
    <summary class="text-secondary">Show as table</summary>
    <table class="table table-sm mt-2 mb-0">
        <thead><tr><th>{{ $axis ?? 'Category' }}</th><th class="text-end">{{ $title }}</th></tr></thead>
        <tbody>
        @foreach ($labels as $i => $label)
            <tr><td>{{ $label }}</td><td class="text-end">{{ $values[$i] === null ? "—" : number_format((float) $values[$i], $decimals) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</details>
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.eipcrBar(@json($id), @json($title), @json(array_values($labels)), @json(array_values($values)), @json($max ?? null), @json($decimals));
        });
    </script>
@endpush
