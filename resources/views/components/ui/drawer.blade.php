@props([
    'name',
    'title',
    'maxWidth' => 'md', // sm: 384px, md: 448px, lg: 512px
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        default => 'max-w-md',
    };
@endphp

<div
    x-data="{ show: false, name: '{{ $name }}' }"
    x-show="show"
    x-on:open-drawer.window="$event.detail == name ? show = true : null"
    x-on:close-drawer.window="$event.detail == name ? show = false : null"
    x-on:keydown.escape.window="show = false"
    x-effect="document.body.classList.toggle('overflow-hidden', show)"
    style="display: none;"
    {{-- z-[60] literal — `z-drawer` bukan utility Tailwind yang valid: token
         --z-drawer cuma ada di :root, di luar @theme, dan z-index bukan
         namespace yang di-generate Tailwind v4. Class itu resolve ke
         z-index:auto (cek CSS hasil build: cuma ada .z-10..z-50). --}}
    class="relative z-[60]"
    aria-labelledby="slide-over-title" role="dialog" aria-modal="true"
>
    <!-- Overlay -->
    <div x-show="show" x-transition.opacity class="fixed inset-0 top-16 bg-text-main/50 transition-opacity"></div>

    <div class="fixed inset-0 top-16 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
            <div class="pointer-events-none fixed top-16 bottom-0 right-0 flex max-w-full pl-10">
                <!-- Drawer Panel -->
                <div 
                    x-show="show"
                    x-transition:enter="transform transition ease-in-out duration-slow"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-normal"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="pointer-events-auto w-screen {{ $maxWidthClass }}"
                    @click.outside="show = false"
                >
                    <div class="flex h-full flex-col overflow-y-scroll bg-surface shadow-md border-l border-border">
                        <div class="px-4 py-4 sm:px-6 border-b border-border bg-surface-muted flex items-start justify-between">
                            <h2 class="text-lg font-semibold text-text-main" id="slide-over-title">{{ $title }}</h2>
                            <div class="ml-3 flex h-7 items-center">
                                <button type="button" @click="show = false" class="relative rounded-md text-text-muted hover:text-text-main focus:outline-none focus:ring-2 focus:ring-primary">
                                    <span class="absolute -inset-2.5"></span>
                                    <span class="sr-only">Close panel</span>
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                        <div class="relative flex-1 px-4 py-6 sm:px-6 text-text-secondary font-ui">
                            {{ $slot }}
                        </div>
                        @if(isset($footer))
                            <div class="border-t border-border px-4 py-4 sm:px-6 bg-surface-muted flex gap-2 justify-end">
                                {{ $footer }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
