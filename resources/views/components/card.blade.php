@props(['title', 'flush' => false, 'id' => null])
<section {{ $attributes->class(['card eipcr-card', 'scroll-target' => $id]) }} @if ($id) id="{{ $id }}" @endif>
    <div class="card-header">
        <h2 class="card-heading">{{ $title }}</h2>
        @isset($actions)
            <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>
    <div @class(['card-body' => ! $flush])>
        {{ $slot }}
    </div>
</section>
