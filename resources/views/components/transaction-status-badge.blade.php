@props(['status'])
@php
    $class = match($status->value) {
        'CONFIRMED' => 'badge-green',
        'CANCELLED' => 'badge-red',
        default => 'badge-amber',
    };
@endphp
<span {{ $attributes->class(['badge', $class]) }}><span class="dot"></span>{{ $status->label() }}</span>
