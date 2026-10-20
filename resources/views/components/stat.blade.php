@props(['label', 'value', 'hint' => null, 'small' => false])
<div {{ $attributes->class(['stat']) }}>
    <div class="stat-label">{{ $label }}</div>
    <div @class(['stat-value', 'small-value' => $small])>{{ $value }}</div>
    @if ($hint)
        <div class="stat-hint">{{ $hint }}</div>
    @endif
</div>
