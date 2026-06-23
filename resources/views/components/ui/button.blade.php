@props([
    'variant' => 'primary', // primary, secondary, danger, etc
    'type' => 'button',
    'href' => null,
])

@php
    $classes = "btn-{$variant}";
    if ($attributes->has('class')) {
        $classes .= ' ' . $attributes->get('class');
    }
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->except('class')->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->except('class')->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
