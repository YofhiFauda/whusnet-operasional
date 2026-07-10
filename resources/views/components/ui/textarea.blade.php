@props([
    'disabled' => false,
    'error' => false,
    'rows' => 3,
])

@php
    $baseClasses = 'flex min-h-[60px] w-full rounded-md border bg-surface px-3 py-2 text-sm font-ui shadow-sm placeholder:text-text-muted focus-visible:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted';
    $stateClasses = $error 
        ? 'border-error text-error focus-visible:ring-error focus-visible:border-error' 
        : 'border-border focus-visible:border-primary focus-visible:ring-primary-border text-text-main';
    $classes = $baseClasses . ' ' . $stateClasses;
@endphp

<textarea rows="{{ $rows }}" {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => $classes]) !!}>{{ $slot }}</textarea>
