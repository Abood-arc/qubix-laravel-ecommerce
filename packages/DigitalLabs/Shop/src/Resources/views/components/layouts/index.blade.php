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

    // Store look (Admin > Configuration > General > Design > Store Look): colours, fonts and
    // corners only, via data-look and the tokens in app.css, plus the look's Google font.
    $storeLook = \DigitalLabs\Shop\Enums\StoreLookEnum::current();

    $fontsUrl = $storeLook->fontsUrl();
@endphp

<!DOCTYPE html>

<html
    lang="{{ app()->getLocale() }}"
    dir="{{ core()->getCurrentLocale()->direction }}"
    data-look="{{ $storeLook->value }}"
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
            href="{{ $fontsUrl }}"
        />

        <link
            rel="stylesheet"
            href="{{ $fontsUrl }}"
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

        @include('shop::components.layouts.category-nav-script', ['categoryTreeStamp' => $categoryTreeStamp])

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

        {{-- No chatbot on the checkout page: nothing should float over the address and payment
             steps, and the page loads lighter without the widget. --}}
        @unless (request()->routeIs('shop.checkout.onepage.index'))
        <script src="https://cdn.zanderio.ai/widget/loader.js" data-id="wdg_bDmt03WxuJCWokGT6wmHrX1O" defer></script>

        {{-- The chat button floats above everything (max z-index), so it covered the cart's subtotal
             and Checkout button. Drawers and modals lock scrolling with body.style.overflow = 'hidden';
             hide the button while one is open. The widget pins display/visibility/opacity on its host
             with inline !important, so those can't be overridden; content-visibility: hidden adds paint
             containment instead, which clips the button (fixed inside the host's shadow root) to the
             empty host: not drawn, not tappable. --}}
        <style>
            body[style*="overflow: hidden"] #zanderio-widget-host {
                content-visibility: hidden;
            }
        </style>

        {{-- Shoppers can drag the chat button out of the way (thumb or mouse). On release it snaps to
             the nearest side edge and the spot is remembered on this device (localStorage), on every
             page. A press that moves under 8px is still a normal tap that opens the chat. The button
             lives in the widget's open shadow root; it is moved with the CSS translate property, which
             stacks with the widget's own hover transform instead of replacing it. --}}
        <script>
            (function () {
                var STORE_KEY = 'qubix.chatLauncher';
                var MARGIN = 12;
                var SLOP = 8;

                var host, root, button;
                var dx = 0, dy = 0;
                var press = null;
                var swallowClick = false;

                function findButton() {
                    return root.querySelector('button[aria-label="Open chat"]')
                        || Array.prototype.find.call(root.querySelectorAll('button'), function (b) {
                            return getComputedStyle(b).position === 'fixed';
                        })
                        || null;
                }

                function setOffset(x, y, animate) {
                    dx = x;
                    dy = y;
                    button.style.transition = animate ? 'transform .15s, translate .25s cubic-bezier(.2, .8, .2, 1)' : 'transform .15s';
                    button.style.translate = x + 'px ' + y + 'px';
                }

                // Where the button sits with no offset (the widget's own spot). Computed left/top and
                // offsetWidth ignore transforms, so the widget's hover-grow doesn't skew the maths.
                function baseRect() {
                    var cs = getComputedStyle(button);

                    return {
                        left: parseFloat(cs.left) || 0,
                        top: parseFloat(cs.top) || 0,
                        width: button.offsetWidth,
                        height: button.offsetHeight,
                    };
                }

                function clampTop(top, height) {
                    return Math.min(Math.max(top, MARGIN), window.innerHeight - MARGIN - height);
                }

                function place(side, topRatio, animate) {
                    var b = baseRect();

                    if (! b.width) {
                        return;
                    }

                    var left = side === 'left' ? MARGIN : window.innerWidth - MARGIN - b.width;
                    var top = clampTop(topRatio * window.innerHeight, b.height);

                    setOffset(left - b.left, top - b.top, animate);
                }

                function load() {
                    try {
                        return JSON.parse(localStorage.getItem(STORE_KEY) || 'null');
                    } catch (e) {
                        return null;
                    }
                }

                function save(side, topRatio) {
                    try {
                        localStorage.setItem(STORE_KEY, JSON.stringify({ side: side, top: topRatio }));
                    } catch (e) {}
                }

                function restore() {
                    var saved = load();

                    if (saved && (saved.side === 'left' || saved.side === 'right') && typeof saved.top === 'number') {
                        place(saved.side, saved.top, false);
                    }
                }

                function onDown(e) {
                    if (! button || ! button.contains(e.target) || (e.pointerType === 'mouse' && e.button !== 0)) {
                        return;
                    }

                    press = { id: e.pointerId, x: e.clientX, y: e.clientY, dx: dx, dy: dy, dragging: false };
                }

                function onMove(e) {
                    if (! press || e.pointerId !== press.id) {
                        return;
                    }

                    var mx = e.clientX - press.x;
                    var my = e.clientY - press.y;

                    if (! press.dragging) {
                        if (Math.abs(mx) < SLOP && Math.abs(my) < SLOP) {
                            return;
                        }

                        press.dragging = true;

                        try {
                            button.setPointerCapture(e.pointerId);
                        } catch (err) {}
                    }

                    var b = baseRect();
                    var left = Math.min(Math.max(b.left + press.dx + mx, MARGIN), window.innerWidth - MARGIN - b.width);
                    var top = clampTop(b.top + press.dy + my, b.height);

                    setOffset(left - b.left, top - b.top, false);
                }

                function onUp(e) {
                    if (! press || e.pointerId !== press.id) {
                        return;
                    }

                    var wasDragging = press.dragging;

                    press = null;

                    if (! wasDragging) {
                        return;
                    }

                    swallowClick = true;
                    setTimeout(function () { swallowClick = false; }, 400);

                    var r = button.getBoundingClientRect();
                    var side = r.left + r.width / 2 < window.innerWidth / 2 ? 'left' : 'right';
                    var topRatio = r.top / window.innerHeight;

                    place(side, topRatio, true);
                    save(side, topRatio);
                }

                function onClick(e) {
                    if (swallowClick) {
                        swallowClick = false;
                        e.stopImmediatePropagation();
                        e.preventDefault();
                    }
                }

                function adopt() {
                    var found = findButton();

                    if (! found || found === button) {
                        return;
                    }

                    button = found;
                    dx = 0;
                    dy = 0;
                    button.style.touchAction = 'none';
                    restore();
                }

                function attach(h) {
                    host = h;
                    root = h.shadowRoot;

                    if (! root) {
                        return;
                    }

                    root.addEventListener('pointerdown', onDown, true);
                    window.addEventListener('pointermove', onMove, true);
                    window.addEventListener('pointerup', onUp, true);
                    window.addEventListener('pointercancel', onUp, true);
                    root.addEventListener('click', onClick, true);

                    new MutationObserver(adopt).observe(root, { childList: true, subtree: true });
                    adopt();

                    window.addEventListener('resize', restore);
                }

                var existing = document.getElementById('zanderio-widget-host');

                if (existing) {
                    attach(existing);
                    return;
                }

                var watcher = new MutationObserver(function () {
                    var h = document.getElementById('zanderio-widget-host');

                    if (h && h.shadowRoot) {
                        watcher.disconnect();
                        attach(h);
                    }
                });

                watcher.observe(document.documentElement, { childList: true, subtree: true });
            })();
        </script>
        @endunless

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
