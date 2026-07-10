@props(['id', 'label' => null, 'description' => null, 'checked' => false])

<div class="flex items-center gap-3" x-data="{ checked: {{ $checked ? 'true' : 'false' }} }">
    <button 
        type="button" 
        id="{{ $id }}"
        role="switch" 
        :aria-checked="checked" 
        @click="checked = !checked"
        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 transition-colors duration-200 ease-in-out border"
        :class="checked ? 'bg-primary border-primary' : 'bg-surface-muted border-border-strong'"
        {{ $attributes->except(['name', 'value']) }}
    >
        <span class="sr-only">{{ $label }}</span>
        <span 
            aria-hidden="true" 
            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            :class="checked ? 'translate-x-2' : '-translate-x-2'"
        ></span>
    </button>
    
    @if($label)
        <div class="flex flex-col">
            <label for="{{ $id }}" class="text-sm font-medium text-text-main cursor-pointer select-none" @click="checked = !checked">
                {{ $label }}
            </label>
            @if($description)
                <p class="text-xs text-text-muted mt-0.5">{{ $description }}</p>
            @endif
        </div>
    @endif
    
    @if($attributes->has('name'))
        <input type="hidden" name="{{ $attributes->get('name') }}" :value="checked ? '{{ $attributes->get('value', 1) }}' : '0'">
    @endif
</div>
