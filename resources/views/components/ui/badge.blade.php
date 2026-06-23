@props([
    'variant' => 'neutral', // success, warning, error, info, neutral
])

@php
    $classes = "badge badge-{$variant}";
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
