@props(['links' => []])

<nav class="flex" aria-label="Breadcrumb">
    <ol role="list" class="flex items-center space-x-2">
        @foreach($links as $index => $link)
            <li>
                <div class="flex items-center">
                    @if($index > 0)
                        <svg class="h-4 w-4 shrink-0 text-text-disabled mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    @endif
                    
                    @if($loop->last || empty($link['href']))
                        <span class="text-sm font-medium text-text-main" aria-current="page">{{ $link['label'] }}</span>
                    @else
                        <a href="{{ $link['href'] }}" class="text-sm font-medium text-text-muted hover:text-text-main transition-colors">
                            {{ $link['label'] }}
                        </a>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</nav>
