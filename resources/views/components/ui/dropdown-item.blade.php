@props(['href' => null])

@php
    $classes = 'block w-full px-4 py-2 text-left text-sm font-ui leading-5 text-text-secondary hover:bg-surface-muted hover:text-text-main focus:outline-none focus:bg-surface-muted transition duration-normal ease-standard';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
