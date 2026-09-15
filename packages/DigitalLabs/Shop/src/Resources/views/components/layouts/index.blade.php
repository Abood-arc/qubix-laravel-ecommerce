@props([
    'hasHeader'  => true,
    'hasFeature' => true,
    'hasFooter'  => true,
    'categoryTreeStamp' => null,
])

@php
    // The home page already computes this (HomeController) and passes it in via
    // the categoryTreeStamp prop, so this only re-queries on every other page.
    $categoryTreeStamp ??= app(\DigitalLabs\Category\Repositories\CategoryRepository::class)->getCategoryTreeStamp();
@endphp

<!DOCTYPE html>

<html
    lang="{{ app()->getLocale() }}"
    dir="{{ core()->getCurrentLocale()->direction }}"
>
    <head>

        {!! view_render_event('qubix.shop.layout.head.before') !!}

        <title>{{ $title ? (\Illuminate\Support\Str::startsWith($title, config('app.name')) ? $title : $title.' | '.config('app.name')) : config('app.name') }}</title>

        <meta charset="UTF-8">

        <meta
            http-equiv="X-UA-Compatible"
            content="IE=edge"
        >
        <meta
            http-equiv="content-language"
            content="{{ app()->getLocale() }}"
        >

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >
        <meta
            name="base-url"
            content="{{ url()->to('/') }}"
        >
        <meta
            name="currency"
            content="{{ core()->getCurrentCurrency()->toJson() }}"
        >
        @stack('meta')

        <link
            rel="icon"
            sizes="16x16"
            href="{{ core()->getCurrentChannel()->favicon_url ?? qubix_asset('images/favicon.ico') }}"
        />

        @qubixVite(['src/Resources/assets/css/app.css', 'src/Resources/assets/js/app.js'])

        <link
            rel="preconnect"
            href="https://fonts.googleapis.com"
            crossorigin
        />

        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin
        />

        <link
            rel="preload" as="style"
            href="https://fonts.googleapis.com/css2?family=Karla:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&family=DM+Serif+Display&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap"
        />

        <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Karla:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&family=DM+Serif+Display&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap"
        />

        @stack('styles')

        <style>
            {!! core()->getConfigData('general.content.custom_scripts.custom_css') !!}
        </style>

        @if(core()->getConfigData('general.content.speculation_rules.enabled'))
            <script type="speculationrules">
                @json(core()->getSpeculationRules(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            </script>
        @endif

        {{--
            Single source of truth for the three localStorage['categories'] writers
            (this stamp, the home page's embedded snapshot below, and the
            v-desktop-category / v-mobile-category components in the header).
            Deliberately inline here rather than pushed onto the 'scripts' stack:
            home/index.blade.php's own @push('scripts') call into this object runs
            *before* <x-shop::layouts> is even reached (it's plain Blade above the
            opening tag), and even a component-slot's pushed content is always
            captured before the component's own template — including the header
            include below — runs. Either way, anything pushed onto 'scripts' cannot
            be relied on to define this before a page's own pushed scripts use it.
            <head> always precedes <body> in the rendered HTML regardless of Blade's
            server-side render order, so defining it here is what actually guarantees
            it exists before any consumer's inline script runs.
        --}}
        <script>
            window.qubixCategoryTreeStamp = @json($categoryTreeStamp);

            window.qubixCategoryNav = {
                STORAGE_KEY: 'categories',

                read(currentStamp) {
                    try {
                        const stored = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || 'null');

                        if (
                            stored
                            && Array.isArray(stored.categories)
                            && stored.categories.length > 0
                            && stored.stamp === currentStamp
                        ) {
                            return stored.categories;
                        }
                    } catch (e) {}

                    return null;
                },

                write(categories, stamp) {
                    try {
                        localStorage.setItem(this.STORAGE_KEY, JSON.stringify({ categories, stamp }));
                    } catch (e) {}
                },

                fetchFresh(url) {
                    return axios.get(url).then((response) => ({
                        categories: Array.isArray(response.data.data) ? response.data.data : [],
                        stamp: response.data.stamp ?? null,
                    }));
                },
            };
        </script>

        {!! view_render_event('qubix.shop.layout.head.after') !!}

    </head>

    <body>
        {!! view_render_event('qubix.shop.layout.body.before') !!}

        <a
            href="#main"
            class="skip-to-main-content-link"
        >
            Skip to main content
        </a>

        <div id="app">
            <!-- Flash Message Blade Component -->
            <x-shop::flash-group />

            <!-- Confirm Modal Blade Component -->
            <x-shop::modal.confirm />

            <!-- Page Header Blade Component -->
            @if ($hasHeader)
                <x-shop::layouts.header />
            @endif

            @if(
                core()->getConfigData('general.gdpr.settings.enabled')
                && core()->getConfigData('general.gdpr.cookie.enabled')
            )
                <x-shop::layouts.cookie />
            @endif

            {!! view_render_event('qubix.shop.layout.content.before') !!}

            <!-- Page Content Blade Component -->
            <main id="main" class="bg-white">
                {{ $slot }}
            </main>

            {!! view_render_event('qubix.shop.layout.content.after') !!}


            <!-- Page Services Blade Component -->
            @if ($hasFeature)
                <x-shop::layouts.services />
            @endif

            <!-- Page Footer Blade Component -->
            @if ($hasFooter)
                <x-shop::layouts.footer />
            @endif
        </div>

        {!! view_render_event('qubix.shop.layout.body.after') !!}

        @stack('scripts')

        <script src="https://cdn.zanderio.ai/widget/loader.js" data-id="wdg_bDmt03WxuJCWokGT6wmHrX1O" defer></script>

        {!! view_render_event('qubix.shop.layout.vue-app-mount.before') !!}
        <script>
            /**
             * Load event, the purpose of using the event is to mount the application
             * after all of our `Vue` components which is present in blade file have
             * been registered in the app. No matter what `app.mount()` should be
             * called in the last.
             */
            window.addEventListener("load", function (event) {
                app.mount("#app");
            });
        </script>

        {!! view_render_event('qubix.shop.layout.vue-app-mount.after') !!}

        <script type="text/javascript">
            {!! core()->getConfigData('general.content.custom_scripts.custom_javascript') !!}
        </script>
    </body>
</html>
