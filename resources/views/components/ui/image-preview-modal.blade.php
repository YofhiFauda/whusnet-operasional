{{--
    Modal preview foto generik — dipicu dari mana saja lewat window event `open-image-preview`
    dengan detail { url, label }. Satu instance per halaman, taruh sekali di root view.

    Contoh trigger:
        <button type="button" @click="$dispatch('open-image-preview', { url: '...', label: 'Foto ODP' })">
--}}
<div
    x-data="{ show: false, url: null, label: null }"
    x-on:open-image-preview.window="show = true; url = $event.detail.url; label = $event.detail.label"
    x-on:keydown.escape.window="show = false"
    x-effect="document.body.classList.toggle('overflow-hidden', show)"
    x-show="show"
    style="display: none;"
    class="fixed inset-0 z-[80] overflow-y-auto"
    aria-labelledby="image-preview-title" role="dialog" aria-modal="true"
>
    <div x-show="show" x-transition.opacity @click="show = false" class="fixed inset-0 bg-slate-950/80"></div>

    <div @click.self="show = false" class="flex min-h-full items-center justify-center p-4">
        <div
            @click.stop
            x-show="show"
            x-transition:enter="ease-out duration-normal"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-fast"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-3xl"
        >
            <div class="flex items-center justify-between mb-2 px-1">
                <p id="image-preview-title" class="text-xs font-bold text-white font-ui" x-text="label"></p>
                <button @click="show = false" type="button" class="text-white/80 hover:text-white">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="rounded-xl overflow-hidden border border-white/10 bg-slate-900 shadow-md">
                <img :src="url" :alt="label" class="w-full max-h-[80vh] object-contain">
            </div>
        </div>
    </div>
</div>
