@props([
    'type' => 'info', // error, warning, info, success
    'title',
    'message' => null,
    'actionLabel' => null,
    'actionUrl' => null,
    'dismissible' => true,
])

@php
    $icon = match($type) {
        'error' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-octagon-alert"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>',
        'warning' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        'success' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-circle"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
        default => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
    };
@endphp

<div x-data="{ show: true }" x-show="show" class="alert-banner alert-banner-{{ $type }}" role="alert" aria-live="{{ $type === 'error' ? 'assertive' : 'polite' }}">
    <div class="alert-banner-icon">
        {!! $icon !!}
    </div>
    <div class="alert-banner-body">
        <p class="alert-banner-title">{{ $title }}</p>
        <div class="alert-banner-message">
            @if(isset($message) && $message)
                {{ $message }}
            @endif
            {{ $slot }}
        </div>
        @if($actionLabel && $actionUrl)
            <div class="alert-banner-actions">
                <a href="{{ $actionUrl }}" class="btn-secondary !py-1 !min-h-0 text-xs">{{ $actionLabel }}</a>
            </div>
        @endif
    </div>
    @if($dismissible)
        <button type="button" @click="show = false" class="alert-banner-dismiss" aria-label="Tutup notifikasi">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    @endif
</div>
