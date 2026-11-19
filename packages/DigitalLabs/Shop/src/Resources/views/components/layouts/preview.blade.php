{{--
    The page around the admin's live previews of the product card and store look (CardPreviewController):
    the storefront's own stylesheet, scripts and look tokens, and nothing else. No flash messages, no
    compare tray, no chat button, no custom scripts, no analytics and no layout hooks, which other
    packages use to add such things. The demo (card/demo) is added last, after every pushed script, so
    it can stand in for the cards' cart and compare before the app starts.
--}}
@props([
    'look',
    'hasHeader' => false,
])

@php
    $categoryTreeStamp = app(\DigitalLabs\Category\Repositories\CategoryRepository::class)->getCategoryTreeStamp();
@endphp

<!DOCTYPE html>

<html
    lang="{{ app()->getLocale() }}"
    dir="{{ core()->getCurrentLocale()->direction }}"
    data-look="{{ $look->value }}"
>
    <head>
        <title>@lang('shop::app.card-preview.title')</title>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >

        <meta
            name="robots"
            content="noindex, nofollow"
        >

        <meta
            name="base-url"
            content="{{ url()->to('/') }}"
        >

        <meta
            name="currency"
            content="{{ core()->getCurrentCurrency()->toJson() }}"
        >

        @qubixVite(['src/Resources/assets/css/app.css', 'src/Resources/assets/js/app.js'])

        @include('shop::components.layouts.fonts', ['look' => $look])

        @stack('styles')

        @include('shop::components.layouts.category-nav-script', ['categoryTreeStamp' => $categoryTreeStamp])
    </head>

    <body>
        <div id="app">
            @if ($hasHeader)
                <x-shop::layouts.header />
            @endif

            <main
                id="main"
                class="bg-white"
            >
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')

        @include('shop::components.products.card.demo')

        <script>
            window.addEventListener('load', () => app.mount('#app'));
        </script>
    </body>
</html>
