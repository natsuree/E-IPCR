{{-- Sortable column header. Params: $key, $label, $filters, $route. --}}
@php
    $active = ($filters['sort'] ?? 'name') === $key;
    $dir = $active && ($filters['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
@endphp
<a href="{{ route($route, array_merge($filters, ['sort' => $key, 'dir' => $dir])) }}#employees" class="text-white text-decoration-none"
   aria-label="Sort by {{ $label }}{{ $active ? (($filters['dir'] ?? 'asc') === 'asc' ? ', currently ascending' : ', currently descending') : '' }}">
    {{ $label }}
    @if ($active)<i class="bi bi-caret-{{ ($filters['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>@endif
</a>
