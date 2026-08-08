@props([
    'align' => 'right',
    'width' => '48',
])

@php
switch ($align) {
    case 'left':
        $alignmentClasses = 'origin-top-left left-0';
        break;
    case 'top':
        $alignmentClasses = 'origin-top';
        break;
    case 'right':
    default:
        $alignmentClasses = 'origin-top-right right-0';
        break;
}

switch ($width) {
    case '48':
        $width = 'w-48';
        break;
    case '56':
        $width = 'w-56';
        break;
    case '64':
        $width = 'w-64';
        break;
}
@endphp

<div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-fast"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-fast"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            {{-- z-[40] literal — lihat catatan `z-drawer` di components/ui/drawer.blade.php --}}
            class="absolute z-[40] mt-2 {{ $width }} rounded-md shadow-sm border border-border bg-surface {{ $alignmentClasses }}"
            style="display: none;">
        <div class="py-1 ring-1 ring-black ring-opacity-5 rounded-md">
            {{ $content }}
        </div>
    </div>
</div>
