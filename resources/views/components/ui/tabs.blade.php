@props(['tabs' => [], 'active' => null])

<div class="border-b border-border">
    <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
        @foreach($tabs as $tab)
            @php
                $isActive = $active === $tab['id'];
            @endphp
            <a 
                href="{{ $tab['href'] ?? '#' }}" 
                class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors duration-normal {{ $isActive ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:border-border-strong hover:text-text-main' }}"
                @if($isActive) aria-current="page" @endif
            >
                @if(isset($tab['icon']))
                    <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 inline-block mr-1"></i>
                @endif
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
