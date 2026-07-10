@props(['text', 'position' => 'top'])

<div x-data="{ tooltipVisible: false }" class="relative inline-block" @mouseenter="tooltipVisible = true" @mouseleave="tooltipVisible = false" @focus="tooltipVisible = true" @blur="tooltipVisible = false">
    {{ $slot }}
    
    <div 
        x-show="tooltipVisible"
        x-transition:enter="transition ease-out duration-fast"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-fast"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-[120] px-2 py-1 text-xs font-medium text-white bg-slate-800 rounded-sm shadow-sm whitespace-nowrap pointer-events-none"
        style="max-width: 250px; white-space: normal;"
        @class([
            'bottom-full left-1/2 -translate-x-1/2 mb-2' => $position === 'top',
            'top-full left-1/2 -translate-x-1/2 mt-2' => $position === 'bottom',
            'right-full top-1/2 -translate-y-1/2 mr-2' => $position === 'left',
            'left-full top-1/2 -translate-y-1/2 ml-2' => $position === 'right',
        ])
        x-cloak
    >
        {{ $text }}
        
        <!-- Arrow -->
        <div class="absolute w-2 h-2 bg-slate-800 transform rotate-45"
            @class([
                '-bottom-1 left-1/2 -translate-x-1/2' => $position === 'top',
                '-top-1 left-1/2 -translate-x-1/2' => $position === 'bottom',
                '-right-1 top-1/2 -translate-y-1/2' => $position === 'left',
                '-left-1 top-1/2 -translate-y-1/2' => $position === 'right',
            ])
        ></div>
    </div>
</div>
