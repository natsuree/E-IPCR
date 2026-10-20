{{--
    Single-series line chart with the same numbers in a table.
    Params: $id, $title, $labels, $values (null = no data), $axis, $max (optional), $decimals (optional), $suffix (optional).
--}}
@php($decimals ??= 0)
@php($suffix ??= '')
<div class="chart-box" role="img" aria-label="{{ $title }} by {{ strtolower($axis ?? 'period') }}">
    <canvas id="{{ $id }}"></canvas>
</div>
<details class="mt-2 small">
    <summary class="text-secondary">Show as table</summary>
    <table class="table table-sm mt-2 mb-0">
        <thead><tr><th>{{ $axis ?? 'Rating period' }}</th><th class="text-end">{{ $title }}</th></tr></thead>
        <tbody>
        @foreach ($labels as $i => $label)
            <tr><td>{{ $label }}</td><td class="text-end">{{ $values[$i] === null ? 'No completed IPCRs' : number_format((float) $values[$i], $decimals).$suffix }}</td></tr>
        @endforeach
        </tbody>
    </table>
</details>
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.eipcrLine(@json($id), @json($title), @json(array_values($labels)), @json(array_values($values)), @json($max ?? null), @json($decimals), @json($suffix));
        });
    </script>
@endpush
