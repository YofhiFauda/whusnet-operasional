@props([
    'padding' => 'default', // compact, default, comfortable
])

@php
    $paddingClasses = match($padding) {
        'compact' => 'p-2 sm:p-3',
        'comfortable' => 'p-4 sm:p-5',
        default => 'p-3 sm:p-4',
    };
@endphp

<div {{ $attributes->merge(['class' => "bg-surface border border-border rounded-md shadow-none {$paddingClasses}"]) }}>
    @if(isset($header))
        <div class="mb-4">
            {{ $header }}
        </div>
    @endif
    
    {{ $slot }}
    
    @if(isset($footer))
        <div class="mt-4 pt-4 border-t border-border">
            {{ $footer }}
        </div>
    @endif
</div>
