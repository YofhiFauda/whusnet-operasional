@props(['href' => '#'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'text-primary font-medium no-underline hover:text-primary-hover hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 rounded-xs transition-colors']) }}>
    {{ $slot }}
</a>
