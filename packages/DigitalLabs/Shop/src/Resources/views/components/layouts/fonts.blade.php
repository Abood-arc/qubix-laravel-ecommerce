{{--
    The page's self-hosted fonts (StoreFonts, config shop.fonts), in the <head> of both shop layouts
    (index, preview): a preload for the faces the page is mostly set in, and the @font-face rules of
    every family the page may use. Inline, so they cost no request and nothing waits for another origin.
--}}
@php
    $fonts = \DigitalLabs\Shop\StoreFonts::forPage($look);
@endphp

@foreach ($fonts->preloads() as $url)
    <link
        rel="preload"
        href="{{ $url }}"
        as="font"
        type="font/woff2"
        crossorigin
    />
@endforeach

<style>
@foreach ($fonts->faces() as $face)
@font-face{font-family:"{{ $face['family'] }}";font-style:normal;font-weight:{{ $face['weight'] }};font-display:swap;src:url("{{ $face['url'] }}") format("woff2");unicode-range:{{ $face['unicode_range'] }}}
@endforeach
</style>
