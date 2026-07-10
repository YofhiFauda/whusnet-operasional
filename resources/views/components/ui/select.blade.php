@props([
    'disabled' => false,
    'error' => false,
])

@php
    $baseClasses = 'flex h-9 w-full items-center justify-between rounded-md border bg-surface px-3 py-2 text-sm font-ui shadow-sm focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted';
    $stateClasses = $error 
        ? 'border-error text-error focus:ring-error focus:border-error' 
        : 'border-border focus:border-primary focus:ring-primary-border text-text-main';
    $classes = $baseClasses . ' ' . $stateClasses;
@endphp

<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => $classes]) !!}>
    {{ $slot }}
</select>
