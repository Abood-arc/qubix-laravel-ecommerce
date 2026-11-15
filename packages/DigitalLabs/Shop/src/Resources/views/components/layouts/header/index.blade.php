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

            /*
             * Phones: the two-row header slides away while the shopper scrolls down the page
             * and comes back as soon as they scroll up. It stays put while the search field
             * has focus or a drawer has locked the page.
             */
            var phone = window.matchMedia('(max-width: 1023px)');
            var lastY = getY();

            function applyScrollState() {
                var header = document.getElementById('main-header');
                if (! header) return;

                var y = getY();

                if (y > 80) {
                    header.classList.add('header-scrolled');
                } else {
                    header.classList.remove('header-scrolled');
                }

                var busy = header.contains(document.activeElement) && document.activeElement !== document.body
                    || document.body.style.overflow === 'hidden';

                if (! phone.matches || busy || y < 160) {
                    header.classList.remove('header-hidden');
                } else if (y > lastY + 6) {
                    header.classList.add('header-hidden');
                } else if (y < lastY - 6) {
                    header.classList.remove('header-hidden');
                }

                if (Math.abs(y - lastY) > 6 || y < 160) {
                    lastY = y;
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

    {{--
        Search suggestions under the header search field (desktop and phone): up to six
        matching products as the shopper types, then "See all results". The field is passed
        in as the slot, so the form, its validation and image search stay as they were.
        Requests carry suggest=1 so keystrokes are not recorded as search terms (the API
        only records a term when `query` is the sole parameter).
    --}}
    <script
        type="text/x-template"
        id="v-search-suggestions-template"
    >
        <div class="relative w-full">
            <slot></slot>

            <div
                class="absolute inset-x-0 top-full z-[80] mt-2 overflow-hidden rounded-xl border border-zinc-200 bg-white text-zinc-900 shadow-[0_18px_40px_-12px_rgba(0,0,0,0.25)]"
                v-if="isOpen"
            >
                <ul
                    :id="listId"
                    role="listbox"
                    class="max-h-[60vh] overflow-y-auto pt-1.5"
                    @mousedown.prevent
                >
                    <li
                        v-for="(product, index) in products"
                        :id="listId + '-' + index"
                        :key="product.id"
                        role="option"
                        :aria-selected="index === active ? 'true' : 'false'"
                    >
                        <a
                            :href="productUrl(product)"
                            class="flex items-center gap-3 px-3 py-2 transition-colors"
                            :class="index === active ? 'bg-sage' : 'hover:bg-zinc-50'"
                            tabindex="-1"
                        >
                            {{-- Product photos carry wide white margins, so the thumbnail is zoomed in a little. --}}
                            <span class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-white">
                                <img
                                    :src="product.base_image?.small_image_url"
                                    alt=""
                                    class="h-full w-full scale-[1.3] object-contain"
                                    loading="lazy"
                                >
                            </span>

                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-semibold"
                                    v-text="product.name"
                                ></span>

                                <span
                                    class="block text-sm text-zinc-600"
                                    v-text="product.prices?.final?.formatted_price ?? product.min_price"
                                ></span>
                            </span>
                        </a>
                    </li>

                    <li
                        class="px-3 py-3 text-sm text-zinc-600"
                        v-if="! products.length"
                        v-text="noResultsText"
                    ></li>

                    <li
                        :id="listId + '-' + products.length"
                        role="option"
                        class="sticky bottom-0 bg-white"
                        :aria-selected="active === products.length ? 'true' : 'false'"
                        v-if="products.length"
                    >
                        <a
                            :href="searchUrl"
                            class="block border-t border-zinc-100 px-3 py-3 text-sm font-semibold text-brand"
                            :class="active === products.length ? 'bg-sage' : 'hover:bg-zinc-50'"
                            tabindex="-1"
                            v-text="seeAllText"
                        ></a>
                    </li>
                </ul>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-search-suggestions', {
            template: '#v-search-suggestions-template',

            data() {
                return {
                    query: '',
                    products: [],
                    isOpen: false,
                    active: -1,
                    listId: 'search-suggestions-' + Math.random().toString(36).slice(2, 8),
                };
            },

            computed: {
                searchUrl() {
                    return "{{ route('shop.search.index') }}?query=" + encodeURIComponent(this.query);
                },

                seeAllText() {
                    return @json(trans('shop::app.components.layouts.header.mobile.see-all-results')).replace(':query', this.query);
                },

                noResultsText() {
                    return @json(trans('shop::app.components.layouts.header.mobile.no-results')).replace(':query', this.query);
                },
            },

            mounted() {
                this.input = this.$el.querySelector('input[name="query"]');

                if (! this.input) {
                    return;
                }

                this.minLength = Math.max(2, parseInt(this.input.getAttribute('minlength') || '0', 10));

                this.input.setAttribute('role', 'combobox');
                this.input.setAttribute('aria-autocomplete', 'list');
                this.input.setAttribute('aria-controls', this.listId);
                this.input.setAttribute('aria-expanded', 'false');
                this.input.setAttribute('autocomplete', 'off');

                this.onInput = () => this.schedule();
                this.onKeydown = (event) => this.keydown(event);
                this.onBlur = () => setTimeout(() => this.close(), 120);
                this.onFocus = () => { if (this.products.length || this.query) this.open(); };

                this.input.addEventListener('input', this.onInput);
                this.input.addEventListener('keydown', this.onKeydown);
                this.input.addEventListener('blur', this.onBlur);
                this.input.addEventListener('focus', this.onFocus);
            },

            beforeUnmount() {
                clearTimeout(this.timer);
                this.controller?.abort();

                if (this.input) {
                    this.input.removeEventListener('input', this.onInput);
                    this.input.removeEventListener('keydown', this.onKeydown);
                    this.input.removeEventListener('blur', this.onBlur);
                    this.input.removeEventListener('focus', this.onFocus);
                }
            },

            methods: {
                productUrl(product) {
                    return "{{ route('shop.product_or_category.index', '') }}/" + product.url_key;
                },

                schedule() {
                    clearTimeout(this.timer);

                    const query = this.input.value.trim();

                    if (query.length < this.minLength) {
                        this.controller?.abort();
                        this.query = query;
                        this.products = [];
                        this.close();

                        return;
                    }

                    this.timer = setTimeout(() => this.fetch(query), 250);
                },

                fetch(query) {
                    this.controller?.abort();
                    this.controller = new AbortController();

                    this.$axios.get("{{ route('shop.api.products.index') }}", {
                        params: { query, limit: 6, suggest: 1 },
                        signal: this.controller.signal,
                    })
                        .then((response) => {
                            this.query = query;
                            this.products = (response.data.data || []).slice(0, 6);
                            this.active = -1;

                            if (document.activeElement === this.input) {
                                this.open();
                            }
                        })
                        .catch(() => {});
                },

                open() {
                    if (this.query.length < this.minLength) {
                        return;
                    }

                    this.isOpen = true;
                    this.input.setAttribute('aria-expanded', 'true');
                },

                close() {
                    this.isOpen = false;
                    this.active = -1;
                    this.input?.setAttribute('aria-expanded', 'false');
                    this.input?.removeAttribute('aria-activedescendant');
                },

                keydown(event) {
                    if (! this.isOpen) {
                        return;
                    }

                    const last = this.products.length ? this.products.length : -1;

                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();

                        const step = event.key === 'ArrowDown' ? 1 : -1;

                        this.active = this.active + step > last ? -1 : (this.active + step < -1 ? last : this.active + step);

                        if (this.active >= 0) {
                            this.input.setAttribute('aria-activedescendant', this.listId + '-' + this.active);
                        } else {
                            this.input.removeAttribute('aria-activedescendant');
                        }
                    } else if (event.key === 'Enter' && this.active >= 0) {
                        event.preventDefault();

                        window.location.href = this.active < this.products.length
                            ? this.productUrl(this.products[this.active])
                            : this.searchUrl;
                    } else if (event.key === 'Escape') {
                        this.close();
                    }
                },
            },
        });
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
