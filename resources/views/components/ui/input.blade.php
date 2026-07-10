@props([
    'disabled' => false,
    'error' => false,
])

@php
    $baseClasses = 'flex h-9 w-full rounded-md border bg-surface px-3 py-1 text-sm font-ui shadow-sm transition-colors placeholder:text-text-muted focus-visible:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted';
    $stateClasses = $error 
        ? 'border-error text-error focus-visible:ring-error focus-visible:border-error' 
        : 'border-border focus-visible:border-primary focus-visible:ring-primary-border';
    $classes = $baseClasses . ' ' . $stateClasses;
@endphp

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => $classes]) !!}>
