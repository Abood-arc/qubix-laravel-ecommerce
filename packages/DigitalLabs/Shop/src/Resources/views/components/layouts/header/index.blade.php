{{--
    window.qubixCategoryNav and window.qubixCategoryTreeStamp — shared by this
    header's nav components, home/index.blade.php's embedded snapshot, and the
    category/product/search pages that land here directly — are defined in
    components/layouts/index.blade.php's <head>, not here. A version pushed onto
    the 'scripts' stack from this file would run *after* a page's own pushed
    scripts that consume it (Blade always captures a component slot's pushes —
    e.g. home/index.blade.php's — before the component's own template, including
    this header include, runs), which caused exactly that ReferenceError on every
    home-page load until fixed.
--}}

{!! view_render_event('qubix.shop.layout.header.before') !!}

<div class="max-lg:hidden">
    <x-shop::layouts.header.desktop.top />
</div>

{{--
    Mobile equivalent of the desktop top bar's promo message. Desktop shows
    general.content.header_offer.title site-wide via the max-lg:hidden block
    above; mobile previously had no equivalent at all (only the homepage's
    unrelated CMS "static content" block happened to carry similar copy).
--}}
@if (core()->getConfigData('general.content.header_offer.title'))
    <div
        class="bg-offer px-4 py-2 text-center text-[13px] font-semibold text-offerInk lg:hidden"
        v-pre
    >
        {{ core()->getConfigData('general.content.header_offer.title') }}

        {{-- An empty redirection_link rendered href="" and reloaded the page; show the link only when it goes somewhere. --}}
        @if (core()->getConfigData('general.content.header_offer.redirection_link'))
            <a
                href="{{ core()->getConfigData('general.content.header_offer.redirection_link') }}"
                class="inline-block py-1 underline underline-offset-2"
            >
                {{ core()->getConfigData('general.content.header_offer.redirection_title') }}
            </a>
        @endif
    </div>
@endif

{{--
    Persistent mobile locale/currency switcher. Previously this UI only
    existed inside the hamburger drawer's footer (header/mobile/index.blade.php,
    ~line 328) — reachable, but hidden behind an extra tap. Desktop shows the
    equivalent dropdowns directly in its always-visible top bar. For a
    bilingual storefront (e.g. jj-bags.com, EN/AR) that extra tap to reach
    language switching is a real gap, not a stylistic one, so this mirrors
    the drawer footer's already-working markup (same v-currency-switcher /
    v-locale-switcher Vue components, globally registered in
    header/desktop/top.blade.php) in the persistent header instead of only
    inside the drawer. Same visibility gate as the drawer version.
--}}
@if (core()->getCurrentChannel()->locales()->count() > 1 || core()->getCurrentChannel()->currencies()->count() > 1)
    <div class="grid w-full grid-cols-[1fr_auto_1fr] items-center justify-items-center border-b border-offerInk/10 bg-offer px-5 text-offerInk lg:hidden">
        <!-- Currency Drawer -->
        <x-shop::drawer position="bottom" width="100%">
            <x-slot:toggle>
                <div
                    class="flex cursor-pointer items-center gap-x-2.5 px-2.5 py-2 text-sm font-medium uppercase"
                    role="button"
                    v-pre
                >
                    {{ core()->getCurrentCurrency()->symbol . ' ' . core()->getCurrentCurrencyCode() }}
                </div>
            </x-slot>

            <x-slot:header>
                <div class="flex items-center justify-between">
                    <p class="text-lg font-semibold">
                        @lang('shop::app.components.layouts.header.mobile.currencies')
                    </p>
                </div>
            </x-slot>

            <x-slot:content class="!px-0">
                <div
                    class="overflow-auto"
                    :style="{ height: getCurrentScreenHeight }"
                >
                    <v-currency-switcher></v-currency-switcher>
                </div>
            </x-slot>
        </x-shop::drawer>

        <span class="h-4 w-0.5 bg-offerInk/20"></span>

        <!-- Locale Drawer -->
        <x-shop::drawer position="bottom" width="100%">
            <x-slot:toggle>
                <div
                    class="flex cursor-pointer items-center gap-x-2.5 px-2.5 py-2 text-sm font-medium uppercase"
                    role="button"
                    v-pre
                >
                    <img
                        src="{{ ! empty(core()->getCurrentLocale()->logo_url)
                                ? core()->getCurrentLocale()->logo_url
                                : qubix_asset('images/default-language.svg')
                            }}"
                        alt="Default locale"
                        width="20"
                        height="14"
                    />

                    {{ core()->getCurrentChannel()->locales()->orderBy('name')->where('code', app()->getLocale())->value('name') }}
                </div>
            </x-slot>

            <x-slot:header>
                <div class="flex items-center justify-between">
                    <p class="text-lg font-semibold">
                        @lang('shop::app.components.layouts.header.mobile.locales')
                    </p>
                </div>
            </x-slot>

            <x-slot:content class="!px-0">
                <div
                    class="overflow-auto"
                    :style="{ height: getCurrentScreenHeight }"
                >
                    <v-locale-switcher></v-locale-switcher>
                </div>
            </x-slot>
        </x-shop::drawer>
    </div>
@endif

<header
    id="main-header"
    class="sticky top-0 z-10 bg-head"
>
    <v-header-switcher>
        <!-- Desktop Header Shimmer (same two rows as the header) -->
        <div class="max-lg:hidden">
            <div class="grid min-h-[76px] grid-cols-[auto_minmax(0,620px)_1fr] items-center gap-x-8 px-10 max-1180:gap-x-5 max-1180:px-8">
                <span class="shimmer block h-12 w-12 rounded-full" role="presentation"></span>

                <span class="shimmer block h-11 w-full rounded-ui" role="presentation"></span>

                <div class="flex justify-end gap-x-4">
                    <span class="shimmer block h-10 w-12 rounded" role="presentation"></span>
                    <span class="shimmer block h-10 w-12 rounded" role="presentation"></span>
                    <span class="shimmer block h-10 w-12 rounded" role="presentation"></span>
                </div>
            </div>

            <div class="site-nav flex h-12 items-center gap-6 bg-nav px-10 max-1180:px-8">
                <span class="shimmer h-5 w-24 rounded" role="presentation"></span>
                <span class="shimmer h-5 w-24 rounded" role="presentation"></span>
                <span class="shimmer h-5 w-24 rounded" role="presentation"></span>
            </div>
        </div>

        <!-- Mobile Header Shimmer (menu, logo, icons; then search) -->
        <div class="lg:hidden">
            <div class="flex h-14 items-center gap-2 px-3">
                <span class="shimmer block h-6 w-6 rounded" role="presentation"></span>
                <span class="shimmer block h-10 w-10 rounded-full" role="presentation"></span>

                <div class="flex gap-x-4 ltr:ml-auto rtl:mr-auto">
                    <span class="shimmer block h-6 w-6 rounded" role="presentation"></span>
                    <span class="shimmer block h-6 w-6 rounded" role="presentation"></span>
                    <span class="shimmer block h-6 w-6 rounded" role="presentation"></span>
                </div>
            </div>

            <div class="px-3 pb-3">
                <span class="shimmer block h-11 w-full rounded-ui" role="presentation"></span>
            </div>
        </div>
    </v-header-switcher>
</header>

{{-- Phones: the top categories as one swipeable row of chips, under the sticky header. --}}
<v-category-chips class="lg:hidden">
    <div class="flex h-[52px] items-center gap-2 overflow-hidden px-3 lg:hidden">
        <span class="shimmer h-9 w-24 shrink-0 rounded-ui" role="presentation"></span>
        <span class="shimmer h-9 w-24 shrink-0 rounded-ui" role="presentation"></span>
        <span class="shimmer h-9 w-24 shrink-0 rounded-ui" role="presentation"></span>
    </div>
</v-category-chips>

{!! view_render_event('qubix.shop.layout.header.after') !!}

@pushOnce('scripts')
    <script>
        (function () {
            /*
             * Re-acquire the header on EVERY call so Vue re-renders never leave
             * us holding a detached/stale element reference.
             *
             * Scroll position is read from document.scrollingElement.scrollTop —
             * the authoritative cross-browser API for the main scroll container.
             * window.scrollY is kept as a fallback.
             */
            function getY() {
                var se = document.scrollingElement;
                if (se) return se.scrollTop;
                return window.scrollY !== undefined
                    ? window.scrollY
                    : (window.pageYOffset || document.documentElement.scrollTop || 0);
            }

            function applyScrollState() {
                var header = document.getElementById('main-header');
                if (! header) return;

                if (getY() > 80) {
                    header.classList.add('header-scrolled');
                } else {
                    header.classList.remove('header-scrolled');
                }
            }

            applyScrollState();

            /* Passive scroll listener instead of per-frame polling, which kept the CPU busy on phones. */
            window.addEventListener('scroll', applyScrollState, { passive: true });

            /* After Vue app.mount (#app on window.load), sync once post-paint */
            window.addEventListener('load', function () {
                requestAnimationFrame(function () {
                    requestAnimationFrame(applyScrollState);
                });
            });
        })();
    </script>
@endPushOnce

@pushOnce('scripts')
    <script 
        type="text/x-template" 
        id="v-header-switcher-template"
    >
        <v-desktop-header v-if="isDesktop"></v-desktop-header>
        
        <v-mobile-header v-else></v-mobile-header>
    </script>

    <script
        type="text/x-template"
        id="v-category-chips-template"
    >
        <nav
            class="lg:hidden"
            aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.categories')"
            v-if="categories.length"
        >
            <ul class="category-chips flex h-[52px] items-center gap-2 overflow-x-auto px-3">
                <li
                    class="shrink-0"
                    v-for="category in categories"
                    :key="category.id"
                >
                    <a
                        :href="category.url"
                        class="flex h-9 items-center whitespace-nowrap rounded-ui px-3.5 text-sm font-semibold transition-colors"
                        :class="isCurrent(category) ? 'bg-brand text-white' : 'bg-sand text-brand'"
                        :aria-current="isCurrent(category) ? 'page' : null"
                        v-text="category.name"
                    ></a>
                </li>
            </ul>
        </nav>
    </script>

    <script type="module">
        app.component('v-category-chips', {
            template: '#v-category-chips-template',

            data() {
                return {
                    categories: [],
                };
            },

            mounted() {
                const stored = window.qubixCategoryNav.read(window.qubixCategoryTreeStamp);

                if (stored) {
                    this.categories = stored;

                    return;
                }

                window.qubixCategoryNav.fetchFresh("{{ route('shop.api.categories.tree') }}")
                    .then(({ categories, stamp }) => {
                        this.categories = categories;

                        window.qubixCategoryNav.write(categories, stamp);
                    })
                    .catch(() => {
                        this.categories = [];
                    });
            },

            methods: {
                isCurrent(category) {
                    try {
                        const path = (url) => new URL(url, window.location.origin).pathname.replace(/\/+$/, '');

                        return path(category.url) === path(window.location.href);
                    } catch (e) {
                        return false;
                    }
                },
            },
        });

        app.component('v-header-switcher', {
            template: '#v-header-switcher-template',

            data() {
                return {
                    isDesktop: window.innerWidth >= 1024
                }
            },

            mounted() {
                this.media = window.matchMedia('(min-width: 1024px)');

                this.media.addEventListener('change', this.handleMedia);
            },

            beforeUnmount() {
                this.media.removeEventListener('change', this.handleMedia);
            },

            methods: {
                handleMedia(e) {
                    this.isDesktop = e.matches;
                }
            }
        });

        app.component('v-desktop-header', {
            template: '#v-desktop-header-template'
        });

        app.component('v-mobile-header', {
            template: '#v-mobile-header-template'
        });
    </script>

    <script 
        type="text/x-template" 
        id="v-desktop-header-template"
    >
        <x-shop::layouts.header.desktop />
    </script>

    <script 
        type="text/x-template" 
        id="v-mobile-header-template"
    >
        <x-shop::layouts.header.mobile />
    </script>
@endPushOnce
