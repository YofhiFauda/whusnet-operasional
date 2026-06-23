@props([
    'title',
    'description' => null,
])

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-text-main font-ui">{{ $title }}</h1>
        @if($description)
            <p class="text-sm text-text-secondary mt-1">{{ $description }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="flex items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
