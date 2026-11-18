{{-- Line icons for the Swipe and Photo deck cards (from the design concepts). Decorative: the buttons carry the names. --}}
@props(['name'])

@php
    $paths = [
        'heart'   => '<path d="M12 20.3s-7.4-4.5-9.1-9.1C1.8 8 3.9 4.8 7.2 4.8c2 0 3.5 1.1 4.8 2.9 1.3-1.8 2.8-2.9 4.8-2.9 3.3 0 5.4 3.2 4.3 6.4-1.7 4.6-9.1 9.1-9.1 9.1z"/>',
        'compare' => '<path d="M7.5 3.8 4 7.3l3.5 3.5M4 7.3h13M16.5 13.2l3.5 3.5-3.5 3.5M20 16.7H7"/>',
        'bag'     => '<path d="M5.2 8.2h13.6l-1.1 11a2 2 0 0 1-2 1.8H8.3a2 2 0 0 1-2-1.8l-1.1-11zM9 8.2V6.6a3 3 0 0 1 6 0v1.6"/>',
        'plus'    => '<path d="M12 5.5v13M5.5 12h13"/>',
        'minus'   => '<path d="M5.5 12h13"/>',
        'check'   => '<path d="M5 12.5l4.3 4.3L19 7.2"/>',
        'ruler'   => '<path d="M3.8 15.7 15.7 3.8l4.5 4.5L8.3 20.2z"/><path d="M7.4 12.1l1.8 1.8M10.2 9.3l1.8 1.8M13 6.5l1.8 1.8"/>',
        'next'    => '<path d="M9.5 5.5 16 12l-6.5 6.5"/>',
        'x'       => '<path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/>',
    ];
@endphp

<svg
    {{ $attributes->merge(['class' => 'pc-ico']) }}
    viewBox="0 0 24 24"
    aria-hidden="true"
    focusable="false"
>{!! $paths[$name] !!}</svg>
