@props(['id', 'label' => null, 'description' => null])

<div class="flex items-start gap-2">
    <div class="flex h-5 items-center">
        <input 
            type="checkbox" 
            id="{{ $id }}"
            {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-border-strong text-primary focus:ring-primary bg-surface transition-colors duration-normal']) }}
        >
    </div>
    @if($label)
        <div class="flex flex-col">
            <label for="{{ $id }}" class="text-sm font-medium text-text-main cursor-pointer select-none">
                {{ $label }}
            </label>
            @if($description)
                <p class="text-xs text-text-muted mt-0.5">{{ $description }}</p>
            @endif
        </div>
    @endif
</div>
