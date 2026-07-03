@props([
    'name',
    'title',
    'maxWidth' => 'md', // sm, md, lg, xl, 2xl
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-md',
    };
@endphp

<div
    x-data="{ show: false, name: '{{ $name }}' }"
    x-show="show"
    x-on:open-modal.window="$event.detail == name ? show = true : null"
    x-on:close-modal.window="$event.detail == name ? show = false : null"
    x-on:keydown.escape.window="show = false"
    style="display: none;"
    class="fixed inset-0 z-modal overflow-y-auto"
    aria-labelledby="modal-title" role="dialog" aria-modal="true"
>
    <!-- Overlay -->
    <div x-show="show" x-transition.opacity @click="show = false" class="fixed inset-0 bg-text-main/50 transition-opacity"></div>

    <div @click.self="show = false" class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div 
            @click.stop
            x-show="show"
            x-transition:enter="ease-out duration-normal"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-fast"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-lg bg-surface text-left shadow-md transition-all w-full {{ $maxWidthClass }} sm:my-8 border border-border"
        >
            <div class="px-4 py-4 border-b border-border flex justify-between items-center bg-surface-muted">
                <h3 class="text-lg font-semibold text-text-main" id="modal-title">{{ $title }}</h3>
                <button @click="show = false" type="button" class="text-text-muted hover:text-text-main">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            
            <div class="px-4 py-5 sm:p-6 text-text-secondary font-ui">
                {{ $slot }}
            </div>
            
            @if(isset($footer))
                <div class="px-4 py-3 border-t border-border bg-surface-muted sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
