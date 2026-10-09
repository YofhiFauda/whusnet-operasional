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
    x-effect="document.body.classList.toggle('overflow-hidden', show)"
    style="display: none;"
    {{-- z-[80] literal — lihat catatan `z-drawer` di components/ui/drawer.blade.php --}}
    class="fixed inset-0 z-[80] overflow-hidden sm:overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4"
    aria-labelledby="modal-title" role="dialog" aria-modal="true"
>
    <!-- Overlay -->
    <div x-show="show" x-transition.opacity @click="show = false" class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/75 backdrop-blur-xs transition-opacity"></div>

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
        class="relative transform overflow-hidden rounded-t-2xl sm:rounded-xl bg-surface text-left shadow-2xl transition-all w-full {{ $maxWidthClass }} border-t sm:border border-border flex flex-col max-h-[90dvh] sm:max-h-[85vh] z-10"
    >
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border flex justify-between items-center bg-surface-muted shrink-0 rounded-t-2xl sm:rounded-t-xl">
            <h3 class="text-base sm:text-lg font-semibold text-text-main" id="modal-title">{{ $title }}</h3>
            <button @click="show = false" type="button" class="text-text-muted hover:text-text-main p-1 cursor-pointer">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        
        <div class="px-4 py-5 sm:p-6 text-text-secondary font-ui overflow-y-auto flex-1 min-h-0 custom-scrollbar">
            {{ $slot }}
        </div>
        
        @if(isset($footer))
            <div class="px-4 py-3 sm:px-6 border-t border-border bg-surface-muted flex flex-col sm:flex-row-reverse gap-2 shrink-0 rounded-b-none sm:rounded-b-xl pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:pb-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
