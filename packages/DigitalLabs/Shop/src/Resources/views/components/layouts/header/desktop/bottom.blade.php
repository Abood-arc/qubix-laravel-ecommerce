{!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.before') !!}

{{--
    Two rows. Row 1: logo, an always-visible search field, then labelled Account, Wishlist,
    Compare and Cart. Row 2: the categories, with their dropdown menus opening below it.
    Colours come from the store look (bg-head / text-headInk, bg-nav / text-navInk).
--}}
@php
    $showWishlist = (bool) core()->getConfigData('customer.settings.wishlist.wishlist_option');
    $showCompare = (bool) core()->getConfigData('catalog.products.settings.compare_option');
@endphp

<div class="w-full">
    <div class="grid min-h-[76px] w-full grid-cols-[auto_minmax(0,620px)_1fr] items-center gap-x-8 px-10 text-headInk max-1180:gap-x-5 max-1180:px-8">
        <!-- Logo -->
        <div class="flex items-center">
            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.logo.before') !!}

            <a
                href="{{ route('shop.home.index') }}"
                aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.qubix')"
                class="inline-flex items-center"
            >
                <img
                    src="{{ core()->getCurrentChannel()->logo_url ?? qubix_asset('images/logo.svg') }}"
                    class="h-12 w-auto object-contain"
                    alt="{{ config('app.name') }}"
                >
            </a>

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.logo.after') !!}
        </div>

        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.search_bar.before') !!}

        <!-- Search: always open -->
        <form
            action="{{ route('shop.search.index') }}"
            class="relative flex w-full items-center text-zinc-700 [&_label.icon-camera]:text-zinc-600 [&_button.icon-camera]:text-zinc-600 [&_svg]:text-zinc-600 [&_svg.animate-spin]:!text-zinc-600 [&_label.absolute]:!top-1/2 [&_label.absolute]:-translate-y-1/2 [&_label.absolute]:mt-0 [&_button.absolute]:!top-1/2 [&_button.absolute]:-translate-y-1/2 [&_button.absolute]:mt-0"
            role="search"
        >
            <label
                for="organic-search"
                class="sr-only"
            >
                @lang('shop::app.components.layouts.header.desktop.bottom.search')
            </label>

            <div class="icon-search pointer-events-none absolute top-1/2 z-[1] -translate-y-1/2 text-xl text-zinc-500 ltr:left-4 rtl:right-4"></div>

            <input
                id="organic-search"
                type="text"
                name="query"
                value="{{ request('query') }}"
                @class([
                    'block h-11 w-full rounded-ui border-0 bg-field text-[15px] text-zinc-900 placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-headInk/50',
                    'px-11' => ! core()->getConfigData('catalog.products.settings.image_search'),
                    'pl-11 pr-14 rtl:pl-14 rtl:pr-11' => core()->getConfigData('catalog.products.settings.image_search'),
                ])
                minlength="{{ core()->getConfigData('catalog.products.search.min_query_length') }}"
                maxlength="{{ core()->getConfigData('catalog.products.search.max_query_length') }}"
                placeholder="@lang('shop::app.components.layouts.header.desktop.bottom.search-text')"
                aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.search-text')"
                aria-required="true"
                pattern="[^\\]+"
                required
            >

            @if (core()->getConfigData('catalog.products.settings.image_search'))
                @include('shop::search.images.index')
            @endif

            <button
                type="submit"
                class="hidden"
                aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.submit')"
            >
            </button>
        </form>

        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.search_bar.after') !!}

        <!-- Labelled actions -->
        <div class="flex items-center justify-end gap-x-1">
            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.profile.before') !!}

            <!-- Account -->
            <x-shop::dropdown position="bottom-{{ core()->getCurrentLocale()->direction === 'ltr' ? 'right' : 'left' }}">
                <x-slot:toggle>
                    <span
                        class="header-action"
                        role="button"
                        tabindex="0"
                    >
                        <span
                            class="icon-users text-2xl"
                            aria-hidden="true"
                        ></span>

                        <span class="header-action-label">@lang('shop::app.components.layouts.header.mobile.account')</span>
                    </span>
                </x-slot>

                <!-- Guest Dropdown -->
                @guest('customer')
                    <x-slot:content>
                        <div class="grid gap-2.5">
                            <p class="text-xl font-dmserif">
                                @lang('shop::app.components.layouts.header.desktop.bottom.welcome-guest')
                            </p>

                            <p class="text-sm text-zinc-500">
                                @lang('shop::app.components.layouts.header.desktop.bottom.dropdown-text')
                            </p>
                        </div>

                        <p class="w-full mt-3 border border-zinc-200"></p>

                        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.customers_action.before') !!}

                        <div class="flex gap-4 mt-6">
                            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.sign_in_button.before') !!}

                            <a
                                href="{{ route('shop.customer.session.create') }}"
                                class="primary-button rounded-2xl px-7 max-md:rounded-lg ltr:ml-0 rtl:mr-0"
                            >
                                @lang('shop::app.components.layouts.header.desktop.bottom.sign-in')
                            </a>

                            <a
                                href="{{ route('shop.customers.register.index') }}"
                                class="secondary-button rounded-2xl px-7 max-md:rounded-lg max-md:py-3 ltr:ml-0 rtl:mr-0"
                            >
                                @lang('shop::app.components.layouts.header.desktop.bottom.sign-up')
                            </a>

                            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.sign_up_button.after') !!}
                        </div>

                        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.customers_action.after') !!}
                    </x-slot>
                @endguest

                <!-- Authenticated Customer Dropdown -->
                @auth('customer')
                    <x-slot:content class="!p-0">
                        <div class="grid gap-2.5 p-5 pb-0">
                            <p class="text-xl font-dmserif" v-pre>
                                @lang('shop::app.components.layouts.header.desktop.bottom.welcome')'
                                {{ auth()->guard('customer')->user()->first_name }}
                            </p>

                            <p class="text-sm text-zinc-500">
                                @lang('shop::app.components.layouts.header.desktop.bottom.dropdown-text')
                            </p>
                        </div>

                        <p class="w-full mt-3 border border-zinc-200"></p>

                        <div class="mt-2.5 grid gap-1 pb-2.5">
                            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.profile_dropdown.links.before') !!}

                            <a
                                class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                href="{{ route('shop.customers.account.profile.index') }}"
                            >
                                @lang('shop::app.components.layouts.header.desktop.bottom.profile')
                            </a>

                            <a
                                class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                href="{{ route('shop.customers.account.orders.index') }}"
                            >
                                @lang('shop::app.components.layouts.header.desktop.bottom.orders')
                            </a>

                            @if ($showWishlist)
                                <a
                                    class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                    href="{{ route('shop.customers.account.wishlist.index') }}"
                                >
                                    @lang('shop::app.components.layouts.header.desktop.bottom.wishlist')
                                </a>
                            @endif

                            <x-shop::form
                                method="DELETE"
                                action="{{ route('shop.customer.session.destroy') }}"
                                id="customerLogout"
                            />

                            <a
                                class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                href="{{ route('shop.customer.session.destroy') }}"
                                onclick="event.preventDefault(); document.getElementById('customerLogout').submit();"
                            >
                                @lang('shop::app.components.layouts.header.desktop.bottom.logout')
                            </a>

                            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.profile_dropdown.links.after') !!}
                        </div>
                    </x-slot>
                @endauth
            </x-shop::dropdown>

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.profile.after') !!}

            <!-- Wishlist (guests are sent to sign in first) -->
            @if ($showWishlist)
                <a
                    href="{{ route('shop.customers.account.wishlist.index') }}"
                    class="header-action"
                >
                    <span
                        class="icon-heart text-2xl"
                        aria-hidden="true"
                    ></span>

                    <span class="header-action-label">@lang('shop::app.components.layouts.header.desktop.bottom.wishlist')</span>
                </a>
            @endif

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.compare.before') !!}

            <!-- Compare -->
            @if ($showCompare)
                <a
                    href="{{ route('shop.compare.index') }}"
                    class="header-action"
                >
                    <span
                        class="icon-compare text-2xl"
                        aria-hidden="true"
                    ></span>

                    <span class="header-action-label">@lang('shop::app.components.layouts.header.desktop.bottom.compare')</span>
                </a>
            @endif

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.compare.after') !!}

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.mini_cart.before') !!}

            <!-- Mini cart -->
            @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                @include('shop::checkout.cart.mini-cart')
            @endif

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.mini_cart.after') !!}
        </div>
    </div>

    <!-- Row 2: categories -->
    <nav
        class="site-nav relative bg-nav px-10 text-navInk max-1180:px-8"
        aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.categories')"
    >
        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.category.before') !!}

        <v-desktop-category>
            <div class="flex h-12 items-center gap-6">
                <span class="w-20 h-5 rounded shimmer" role="presentation"></span>
                <span class="w-20 h-5 rounded shimmer" role="presentation"></span>
                <span class="w-20 h-5 rounded shimmer" role="presentation"></span>
            </div>
        </v-desktop-category>

        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.category.after') !!}
    </nav>
</div>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-desktop-category-template"
    >
        <!-- Loading State -->
        <div
            class="flex h-12 items-center gap-6"
            v-if="isLoading"
        >
            <span class="w-20 h-5 rounded shimmer" role="presentation"></span>
            <span class="w-20 h-5 rounded shimmer" role="presentation"></span>
            <span class="w-20 h-5 rounded shimmer" role="presentation"></span>
        </div>

        <!-- Default category mega-menu layout -->
        <div
            class="velocity-nav-category-list flex flex-wrap items-center gap-x-2 ltr:-ml-2.5 rtl:-mr-2.5"
            v-else-if="'{{ core()->getConfigData('general.design.categories.category_view') }}' !== 'sidebar'"
        >
            <div
                class="velocity-nav-category-group group flex h-12 items-center"
                v-for="category in categories"
            >
                {{-- One hover signal: an underline that grows under the label (it used to be a tinted box plus a separate border 14px lower). --}}
                <span class="flex h-full items-center">
                    <a
                        :href="category.url"
                        :aria-current="isCurrent(category) ? 'page' : null"
                        :class="{ 'after:scale-x-100': isCurrent(category) }"
                        class="velocity-nav-category-trigger relative inline-flex items-center whitespace-nowrap px-2.5 py-2 text-[15px] font-semibold text-navInk after:absolute after:inset-x-2.5 after:bottom-0.5 after:h-0.5 after:origin-left after:scale-x-0 after:rounded-full after:bg-navInk after:transition-transform after:duration-200 after:ease-[cubic-bezier(0.23,1,0.32,1)] after:content-[''] hover:after:scale-x-100 focus-visible:outline-none focus-visible:after:scale-x-100 group-hover:after:scale-x-100 rtl:after:origin-right sm:max-w-none"
                    >
                        @{{ category.name }}
                    </a>
                </span>

                <div
                    class="velocity-nav-megamenu pointer-events-none absolute inset-x-0 top-full z-[60] max-h-[min(580px,80vh)] -translate-y-1 overflow-y-auto border-t border-zinc-200 bg-white px-10 py-8 text-zinc-900 opacity-0 shadow-[0_18px_30px_-18px_rgba(20,40,30,0.35)] transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)] group-hover:pointer-events-auto group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:pointer-events-auto group-focus-within:translate-y-0 group-focus-within:opacity-100"
                    v-if="category.children && category.children.length"
                >
                    <div class="flex flex-wrap justify-start gap-x-12 gap-y-10 xl:gap-x-16">
                        <div
                            class="velocity-nav-megamenu-col grid min-w-[160px] max-w-[220px] flex-1 grid-cols-1 content-start gap-y-3"
                            v-for="pairCategoryChildren in pairCategoryChildren(category)"
                        >
                            <template v-for="secondLevelCategory in pairCategoryChildren">
                                <div>
                                    <p class="velocity-nav-megamenu-title font-semibold text-zinc-900">
                                        <a
                                            class="velocity-nav-megamenu-title-link inline-block pb-1 text-base font-semibold leading-snug tracking-normal transition-colors hover:text-brand"
                                            :href="secondLevelCategory.url"
                                        >
                                            @{{ secondLevelCategory.name }}
                                        </a>
                                    </p>

                                    <ul
                                        class="mt-3 grid grid-cols-1 gap-1"
                                        v-if="secondLevelCategory.children && secondLevelCategory.children.length"
                                    >
                                        <li v-for="thirdLevelCategory in secondLevelCategory.children">
                                            <a
                                                class="velocity-nav-megalnk block rounded-lg px-2 py-1.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-sage hover:text-brand"
                                                :href="thirdLevelCategory.url"
                                            >
                                                @{{ thirdLevelCategory.name }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar category layout -->
        <div v-else>
            <div class="flex items-center">
                <!-- "All" button opens category drawer -->
                <div
                    class="flex h-12 cursor-pointer items-center border-b-2 border-transparent hover:border-navInk"
                    @click="toggleCategoryDrawer"
                >
                    <span class="flex items-center gap-1 px-5 uppercase">
                        <span class="text-xl icon-hamburger"></span>
                        @lang('shop::app.components.layouts.header.desktop.bottom.all')
                    </span>
                </div>

                <!-- First 4 categories in main nav -->
                <div
                    class="group relative flex h-12 items-center border-b-2 border-transparent hover:border-navInk"
                    v-for="category in categories.slice(0, 4)"
                >
                    <span>
                        <a
                            :href="category.url"
                            class="velocity-nav-category-trigger-sidebar inline-block px-5 py-2 text-[15px] font-semibold text-navInk hover:text-navInk/80"
                        >
                            @{{ category.name }}
                        </a>
                    </span>

                    <!-- Category dropdown -->
                    <div
                        class="pointer-events-none absolute top-12 z-[1] max-h-[580px] w-max max-w-[1260px] translate-y-1 overflow-auto rounded-b-xl border-t border-zinc-100 bg-white p-9 opacity-0 shadow-[0_6px_20px_rgba(6,12,59,0.12)] transition duration-300 ease-out group-hover:pointer-events-auto group-hover:translate-y-0 group-hover:opacity-100 group-hover:duration-200 group-hover:ease-in ltr:-left-9 rtl:-right-9"
                        v-if="category.children && category.children.length"
                    >
                        <div class="flex justify-between gap-x-[70px]">
                            <div
                                class="grid w-full min-w-max max-w-[150px] flex-auto grid-cols-[1fr] content-start gap-5"
                                v-for="pairCategoryChildren in pairCategoryChildren(category)"
                            >
                                <template v-for="secondLevelCategory in pairCategoryChildren">
                                    <p class="font-medium text-navyBlue">
                                        <a :href="secondLevelCategory.url">
                                            @{{ secondLevelCategory.name }}
                                        </a>
                                    </p>

                                    <ul
                                        class="grid grid-cols-[1fr] gap-3"
                                        v-if="secondLevelCategory.children && secondLevelCategory.children.length"
                                    >
                                        <li
                                            class="text-sm font-medium text-zinc-500 hover:text-navyBlue"
                                            v-for="thirdLevelCategory in secondLevelCategory.children"
                                        >
                                            <a :href="thirdLevelCategory.url">
                                                @{{ thirdLevelCategory.name }}
                                            </a>
                                        </li>
                                    </ul>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Drawer -->
            <x-shop::drawer
                position="left"
                width="400px"
                ::is-active="isDrawerActive"
                @toggle="onDrawerToggle"
                @close="onDrawerClose"
            >
                <x-slot:toggle></x-slot>

                <x-slot:header class="border-b border-gray-200">
                    <div class="flex items-center justify-between w-full">
                        <p class="text-xl font-medium">
                            @lang('shop::app.components.layouts.header.desktop.bottom.categories')
                        </p>
                    </div>
                </x-slot>

                <x-slot:content class="!px-0">
                    <div class="relative h-full overflow-hidden">
                        <div
                            class="flex h-full transition-transform duration-300"
                            :class="{
                                'ltr:translate-x-0 rtl:translate-x-0': currentViewLevel !== 'third',
                                'ltr:-translate-x-full rtl:translate-x-full': currentViewLevel === 'third'
                            }"
                        >
                            <!-- First level -->
                            <div class="h-[calc(100vh-74px)] w-full flex-shrink-0 overflow-auto">
                                <div class="py-4">
                                    <div
                                        v-for="category in categories"
                                        :key="category.id"
                                        :class="{'mb-2': category.children && category.children.length}"
                                    >
                                        <div class="flex items-center justify-between px-6 py-2 transition-colors duration-200 cursor-pointer hover:bg-zinc-50">
                                            <a
                                                :href="category.url"
                                                class="text-base font-medium text-navyBlue"
                                            >
                                                @{{ category.name }}
                                            </a>
                                        </div>

                                        <!-- Second level -->
                                        <div v-if="category.children && category.children.length">
                                            <div
                                                v-for="secondLevelCategory in category.children"
                                                :key="secondLevelCategory.id"
                                            >
                                                <div
                                                    class="flex items-center justify-between px-6 py-2 transition-colors duration-200 cursor-pointer hover:bg-zinc-50"
                                                    @click="showThirdLevel(secondLevelCategory, category, $event)"
                                                >
                                                    <a
                                                        :href="secondLevelCategory.url"
                                                        class="text-sm font-normal text-zinc-600"
                                                    >
                                                        @{{ secondLevelCategory.name }}
                                                    </a>

                                                    <span
                                                        v-if="secondLevelCategory.children && secondLevelCategory.children.length"
                                                        class="icon-arrow-right rtl:icon-arrow-left text-zinc-400"
                                                    ></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Third level -->
                            <div
                                class="flex-shrink-0 w-full h-full"
                                v-if="currentViewLevel === 'third'"
                            >
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <button
                                        @click="goBackToMainView"
                                        class="flex items-center justify-center gap-2 focus:outline-none"
                                        aria-label="Go back"
                                    >
                                        <span class="text-lg icon-arrow-left rtl:icon-arrow-right"></span>
                                        <p class="text-base font-medium text-navyBlue">
                                            @lang('shop::app.components.layouts.header.desktop.bottom.back-button')
                                        </p>
                                    </button>
                                </div>

                                <div class="py-4">
                                    <div
                                        v-for="thirdLevelCategory in currentSecondLevelCategory?.children"
                                        :key="thirdLevelCategory.id"
                                        class="mb-2"
                                    >
                                        <a
                                            :href="thirdLevelCategory.url"
                                            class="block px-6 py-2 text-sm text-zinc-600 transition-colors duration-200 hover:bg-zinc-50 hover:text-navyBlue"
                                        >
                                            @{{ thirdLevelCategory.name }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-slot>
            </x-shop::drawer>
        </div>
    </script>

    <script type="module">
        app.component('v-desktop-category', {
            template: '#v-desktop-category-template',

            data() {
                return {
                    isLoading: true,
                    categories: [],
                    isDrawerActive: false,
                    currentViewLevel: 'main',
                    currentSecondLevelCategory: null,
                    currentParentCategory: null
                }
            },

            mounted() {
                this.initCategories();
            },

            methods: {
                initCategories() {
                    const stored = window.qubixCategoryNav.read(window.qubixCategoryTreeStamp);

                    if (stored) {
                        this.categories = stored;
                        this.isLoading = false;

                        return;
                    }

                    this.getCategories();
                },

                getCategories() {
                    window.qubixCategoryNav.fetchFresh("{{ route('shop.api.categories.tree') }}")
                        .then(({ categories, stamp }) => {
                            this.isLoading = false;
                            this.categories = categories;
                            window.qubixCategoryNav.write(categories, stamp);
                        })
                        .catch(error => {
                            this.isLoading = false;
                            this.categories = [];
                            console.log(error);
                        });
                },

                /* The category whose page this is gets the underline. */
                isCurrent(category) {
                    try {
                        const path = (url) => new URL(url, window.location.origin).pathname.replace(/\/+$/, '');

                        return path(category.url) === path(window.location.href);
                    } catch (e) {
                        return false;
                    }
                },

                pairCategoryChildren(category) {
                    if (! category.children) return [];

                    return category.children.reduce((result, value, index, array) => {
                        if (index % 2 === 0) {
                            result.push(array.slice(index, index + 2));
                        }
                        return result;
                    }, []);
                },

                toggleCategoryDrawer() {
                    this.isDrawerActive = !this.isDrawerActive;
                    if (this.isDrawerActive) {
                        this.currentViewLevel = 'main';
                    }
                },

                onDrawerToggle(event) {
                    this.isDrawerActive = event.isActive;
                },

                onDrawerClose(event) {
                    this.isDrawerActive = false;
                },

                showThirdLevel(secondLevelCategory, parentCategory, event) {
                    if (secondLevelCategory.children && secondLevelCategory.children.length) {
                        this.currentSecondLevelCategory = secondLevelCategory;
                        this.currentParentCategory = parentCategory;
                        this.currentViewLevel = 'third';

                        if (event) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                    }
                },

                goBackToMainView() {
                    this.currentViewLevel = 'main';
                }
            },
        });
    </script>
@endPushOnce
{!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.after') !!}
