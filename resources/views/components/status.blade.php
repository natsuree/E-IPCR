@props(['status'])
@php
    if ($status instanceof \App\Enums\IpcrStatus) {
        $label = $status->label();
        $class = $status->badge();
    } elseif ($status === 'not_started' || $status === null) {
        $label = 'Not started';
        $class = 'badge-status-notstarted';
    } else {
        $enum = \App\Enums\IpcrStatus::from($status);
        $label = $enum->label();
        $class = $enum->badge();
    }
@endphp
<span {{ $attributes->class(['badge badge-status', $class]) }}>{{ $label }}</span>
