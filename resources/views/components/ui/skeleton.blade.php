@props([
    'type' => 'text-sm', // text-sm, text-md, text-lg, text-xl, text-2xl, circle, badge, btn
    'width' => 'full', // full, or specific tailwind width like '24', '1/2'
])

@php
    $typeClasses = "skeleton-{$type}";
    $widthClass = $width === 'full' ? 'w-full' : "w-{$width}";
@endphp

<div {{ $attributes->merge(['class' => "skeleton {$typeClasses} {$widthClass}"]) }}></div>
