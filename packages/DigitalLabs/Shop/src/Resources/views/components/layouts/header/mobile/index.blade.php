@php
    $showCompare = (bool) core()->getConfigData('catalog.products.settings.compare_option');
    $showWishlist = (bool) core()->getConfigData('customer.settings.wishlist.wishlist_option');
@endphp

<div class="flex min-w-0 w-full flex-wrap gap-4 pt-6 pb-4 shadow-sm lg:hidden">
    <div class="flex w-full min-w-0 items-center justify-between px-4 text-white">
        <!-- Left Navigation -->
        <div class="flex items-center gap-x-1.5">
            {!! view_render_event('qubix.shop.components.layouts.header.mobile.drawer.before') !!}

            <v-mobile-drawer></v-mobile-drawer>

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.drawer.after') !!}

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.logo.before') !!}

            <a
                href="{{ route('shop.home.index') }}"
                class="inline-flex items-center"
                aria-label="@lang('shop::app.components.layouts.header.mobile.qubix')"
            >
                <img
                    src="{{ core()->getCurrentChannel()->logo_url ?? qubix_asset('images/logo.svg') }}"
                    alt="{{ config('app.name') }}"
                    style="height: 48px; width: auto; object-fit: contain;"
                >
            </a>

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.logo.after') !!}
        </div>

        <!-- Right Navigation -->
        <div class="flex items-center gap-x-5 max-md:gap-x-4">
            {!! view_render_event('qubix.shop.components.layouts.header.mobile.search.before') !!}

            {{--
                Full-width viewport strip: absolute + vw breakout fails here (nested flex + padding).
                `fixed inset-x-0 w-full` aligns to viewport; visibility follows native `details[open]`
                via app.css selectors (Tailwind group-open unreliable on v3.3).
            --}}
            <details class="header-search mobile-header-details-search relative z-[60]">
                <summary
                    class="inline-flex h-10 w-10 list-none cursor-pointer items-center justify-center rounded-full text-white transition-colors hover:bg-white/10"
                    aria-label="@lang('shop::app.components.layouts.header.mobile.search')"
                >
                    <span class="mobile-search-mag icon-search text-2xl"></span>
                    <span class="mobile-search-cancel icon-cancel hidden text-2xl"></span>
                </summary>

                <div
                    class="mobile-search-panel fixed inset-x-0 z-[70] hidden w-full max-w-none flex-col border-b border-white/20 bg-[#1f5f4f] px-4 py-3 shadow-[0_14px_32px_rgba(10,30,24,0.35)]"
                    style="top: calc(env(safe-area-inset-top, 0px) + 5.75rem)"
                >
                    <form
                        action="{{ route('shop.search.index') }}"
                        class="relative flex items-center text-white [&_.icon-camera]:text-white [&_label.icon-camera]:text-white"
                        role="search"
                    >
                        <label
                            for="shop-header-search-mobile-panel"
                            class="sr-only"
                        >
                            @lang('shop::app.components.layouts.header.mobile.search')
                        </label>

                        <div
                            class="icon-search pointer-events-none absolute top-[0.72rem] text-xl text-white ltr:left-4 rtl:right-4"
                        ></div>

                        <input
                            id="shop-header-search-mobile-panel"
                            type="search"
                            name="query"
                            value="{{ request('query') }}"
                            class="block w-full rounded-xl border border-white/30 bg-white/10 px-11 py-3 text-sm font-medium text-white placeholder:text-white/70 transition-all hover:border-white/50 focus:border-white focus:outline-none"
                            minlength="{{ core()->getConfigData('catalog.products.search.min_query_length') }}"
                            maxlength="{{ core()->getConfigData('catalog.products.search.max_query_length') }}"
                            placeholder="@lang('shop::app.components.layouts.header.mobile.search-text')"
                            aria-label="@lang('shop::app.components.layouts.header.mobile.search-text')"
                            aria-required="true"
                            pattern="[^\\]+"
                            autocomplete="search"
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
                </div>
            </details>

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.search.after') !!}

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.compare.before') !!}

            @if($showCompare)
                <a
                    href="{{ route('shop.compare.index') }}"
                    aria-label="@lang('shop::app.components.layouts.header.mobile.compare')"
                >
                    <span class="text-2xl cursor-pointer text-white icon-compare"></span>
                </a>
            @endif

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.compare.after') !!}

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.mini_cart.before') !!}

            @if(core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                @include('shop::checkout.cart.mini-cart')
            @endif

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.mini_cart.after') !!}

            <!-- Profile — tablet (768px–1023px): dropdown -->
            <div class="max-md:hidden">
                <x-shop::dropdown position="bottom-{{ core()->getCurrentLocale()->direction === 'ltr' ? 'right' : 'left' }}">
                    <x-slot:toggle>
                        <span class="text-2xl cursor-pointer text-white icon-users"></span>
                    </x-slot>

                    @guest('customer')
                        <x-slot:content>
                            <div class="grid gap-2.5">
                                <p class="text-xl font-dmserif">
                                    @lang('shop::app.components.layouts.header.mobile.welcome-guest')
                                </p>

                                <p class="text-sm text-zinc-500">
                                    @lang('shop::app.components.layouts.header.mobile.dropdown-text')
                                </p>
                            </div>

                            <p class="w-full mt-3 border border-zinc-200"></p>

                            {!! view_render_event('qubix.shop.components.layouts.header.mobile.index.customers_action.before') !!}

                            <div class="flex gap-4 mt-6">
                                {!! view_render_event('qubix.shop.components.layouts.header.mobile.index.sign_in_button.before') !!}

                                <a
                                    href="{{ route('shop.customer.session.create') }}"
                                    class="primary-button rounded-2xl px-7 max-md:rounded-lg ltr:ml-0 rtl:mr-0"
                                >
                                    @lang('shop::app.components.layouts.header.mobile.sign-in')
                                </a>

                                <a
                                    href="{{ route('shop.customers.register.index') }}"
                                    class="secondary-button rounded-2xl px-7 max-md:rounded-lg max-md:py-3 ltr:ml-0 rtl:mr-0"
                                >
                                    @lang('shop::app.components.layouts.header.mobile.sign-up')
                                </a>

                                {!! view_render_event('qubix.shop.components.layouts.header.mobile.index.sign_in_button.after') !!}
                            </div>

                            {!! view_render_event('qubix.shop.components.layouts.header.mobile.index.customers_action.after') !!}
                        </x-slot>
                    @endguest

                    @auth('customer')
                        <x-slot:content class="!p-0">
                            <div class="grid gap-2.5 p-5 pb-0">
                                <p class="text-xl font-dmserif" v-pre>
                                    @lang('shop::app.components.layouts.header.mobile.welcome')'
                                    {{ auth()->guard('customer')->user()->first_name }}
                                </p>

                                <p class="text-sm text-zinc-500">
                                    @lang('shop::app.components.layouts.header.mobile.dropdown-text')
                                </p>
                            </div>

                            <p class="w-full mt-3 border border-zinc-200"></p>

                            <div class="mt-2.5 grid gap-1 pb-2.5">
                                {!! view_render_event('qubix.shop.components.layouts.header.mobile.index.profile_dropdown.links.before') !!}

                                <a
                                    class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                    href="{{ route('shop.customers.account.profile.index') }}"
                                >
                                    @lang('shop::app.components.layouts.header.mobile.profile')
                                </a>

                                <a
                                    class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                    href="{{ route('shop.customers.account.orders.index') }}"
                                >
                                    @lang('shop::app.components.layouts.header.mobile.orders')
                                </a>

                                @if ($showWishlist)
                                    <a
                                        class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                        href="{{ route('shop.customers.account.wishlist.index') }}"
                                    >
                                        @lang('shop::app.components.layouts.header.mobile.wishlist')
                                    </a>
                                @endif

                                @auth('customer')
                                    <x-shop::form
                                        method="DELETE"
                                        action="{{ route('shop.customer.session.destroy') }}"
                                        id="customerLogoutMobile"
                                    />

                                    <a
                                        class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                        href="{{ route('shop.customer.session.destroy') }}"
                                        onclick="event.preventDefault(); document.getElementById('customerLogoutMobile').submit();"
                                    >
                                        @lang('shop::app.components.layouts.header.mobile.logout')
                                    </a>
                                @endauth

                                {!! view_render_event('qubix.shop.components.layouts.header.mobile.index.profile_dropdown.links.after') !!}
                            </div>
                        </x-slot>
                    @endauth
                </x-shop::dropdown>
            </div>

            <!-- Profile — small screen (<768px): direct link -->
            <div class="md:hidden">
                @guest('customer')
                    <a
                        href="{{ route('shop.customer.session.create') }}"
                        aria-label="@lang('shop::app.components.layouts.header.mobile.account')"
                    >
                        <span class="text-2xl cursor-pointer text-white icon-users"></span>
                    </a>
                @endguest

                @auth('customer')
                    <a
                        href="{{ route('shop.customers.account.index') }}"
                        aria-label="@lang('shop::app.components.layouts.header.mobile.account')"
                    >
                        <span class="text-2xl cursor-pointer text-white icon-users"></span>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-mobile-drawer-template">
        <x-shop::drawer
            position="left"
            width="100%"
            panel-class="shop-mobile-nav-drawer bg-[#1f5f4f]"
            @close="onDrawerClose"
        >
            <x-slot:toggle>
                <span class="text-2xl cursor-pointer text-white icon-hamburger"></span>
            </x-slot>

            <x-slot:header class="!border-white/15 text-white [&_.icon-cancel]:text-white [&_.icon-cancel]:opacity-95">
                <div class="flex items-center justify-between">
                    <a href="{{ route('shop.home.index') }}">
                        <img
                            src="{{ core()->getCurrentChannel()->logo_url ?? qubix_asset('images/logo.svg') }}"
                            alt="{{ config('app.name') }}"
                            style="height: 48px; width: auto; object-fit: contain;"
                            class="rounded-lg bg-white px-2.5 py-2 shadow-[0_3px_10px_rgba(0,0,0,0.18)]"
                        >
                    </a>
                </div>
            </x-slot>

            <x-slot:content class="!p-0 shop-mobile-nav-drawer-content">
                <!-- Account Profile Section -->
                <div class="border-b border-white/15 p-4">
                    <div class="grid grid-cols-[auto_1fr] items-center gap-4 rounded-xl border border-white/20 bg-white/5 p-2.5">
                        <div>
                            <img
                                src="{{ auth()->user()?->image_url ?? qubix_asset('images/user-placeholder.png') }}"
                                class="h-[60px] w-[60px] rounded-full"
                            >
                        </div>

                        @guest('customer')
                            <a
                                href="{{ route('shop.customer.session.create') }}"
                                class="flex text-base font-medium text-white"
                            >
                                @lang('shop::app.components.layouts.header.mobile.login')

                                <i class="icon-double-arrow text-2xl text-white/90 ltr:ml-2.5 rtl:mr-2.5"></i>
                            </a>
                        @endguest

                        @auth('customer')
                            <div
                                class="flex flex-col justify-between gap-2.5 max-md:gap-0"
                                v-pre
                            >
                                <p class="text-2xl break-all font-medium max-md:text-xl text-white">Hello! {{ auth()->user()?->first_name }}</p>

                                <p class="no-underline max-md:text-sm text-white/65">{{ auth()->user()?->email }}</p>
                            </div>
                        @endauth
                    </div>
                </div>

                {!! view_render_event('qubix.shop.components.layouts.header.mobile.drawer.categories.before') !!}

                <v-mobile-category ref="mobileCategory"></v-mobile-category>

                {!! view_render_event('qubix.shop.components.layouts.header.mobile.drawer.categories.after') !!}
            </x-slot>

            <x-slot:footer>
                <!-- Locale & Currency -->
                @if(core()->getCurrentChannel()->locales()->count() > 1 || core()->getCurrentChannel()->currencies()->count() > 1)
                    <div class="fixed bottom-0 z-10 grid w-full max-w-full grid-cols-[1fr_auto_1fr] items-center justify-items-center border-t border-white/15 bg-[#184a3d] px-5 text-white ltr:left-0 rtl:right-0">
                        <!-- Currency Drawer -->
                        <x-shop::drawer position="bottom" width="100%">
                            <x-slot:toggle>
                                <div
                                    class="flex cursor-pointer items-center gap-x-2.5 px-2.5 py-3.5 text-lg font-medium uppercase max-md:py-3 max-sm:text-base"
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

                        <span class="h-5 w-0.5 bg-white/25"></span>

                        <!-- Locale Drawer -->
                        <x-shop::drawer position="bottom" width="100%">
                            <x-slot:toggle>
                                <div
                                    class="flex cursor-pointer items-center gap-x-2.5 px-2.5 py-3.5 text-lg font-medium uppercase max-md:py-3 max-sm:text-base"
                                    role="button"
                                    v-pre
                                >
                                    <img
                                        src="{{ ! empty(core()->getCurrentLocale()->logo_url)
                                                ? core()->getCurrentLocale()->logo_url
                                                : qubix_asset('images/default-language.svg')
                                            }}"
                                        alt="Default locale"
                                        width="24"
                                        height="16"
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
            </x-slot>
        </x-shop::drawer>
    </script>

    <script
        type="text/x-template"
        id="v-mobile-category-template"
    >
        <div class="relative h-full overflow-hidden">
            <div
                class="flex h-full transition-transform duration-300"
                :class="{
                    'ltr:translate-x-0 rtl:translate-x-0': currentViewLevel !== 'third',
                    'ltr:-translate-x-full rtl:translate-x-full': currentViewLevel === 'third'
                }"
            >
                <!-- First level -->
                <div class="flex-shrink-0 w-full h-full px-6 overflow-auto">
                    <div class="py-4">
                        <div
                            v-for="category in categories"
                            :key="category.id"
                            :class="{'mb-2': category.children && category.children.length}"
                        >
                            <div class="flex items-center justify-between py-2 transition-colors duration-200 cursor-pointer">
                                <a :href="category.url" class="text-base font-medium text-white hover:text-white/90">
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
                                        class="flex items-center justify-between py-2 transition-colors duration-200 cursor-pointer"
                                        @click="showThirdLevel(secondLevelCategory, category, $event)"
                                    >
                                        <a :href="secondLevelCategory.url" class="text-sm font-normal text-white/85 hover:text-white">
                                            @{{ secondLevelCategory.name }}
                                        </a>

                                        <span
                                            v-if="secondLevelCategory.children && secondLevelCategory.children.length"
                                            class="icon-arrow-right rtl:icon-arrow-left text-white/50"
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
                    <div class="border-b border-white/15 px-6 py-4">
                        <button
                            @click="goBackToMainView"
                            class="flex items-center justify-center gap-2 text-white focus:outline-none"
                            aria-label="Go back"
                        >
                            <span class="text-lg icon-arrow-left rtl:icon-arrow-right text-white"></span>
                            <div class="text-base font-medium text-white">
                                @lang('shop::app.components.layouts.header.mobile.back-button')
                            </div>
                        </button>
                    </div>

                    <div class="px-6 py-4">
                        <div
                            v-for="thirdLevelCategory in currentSecondLevelCategory?.children"
                            :key="thirdLevelCategory.id"
                            class="mb-2"
                        >
                            <a
                                :href="thirdLevelCategory.url"
                                class="block py-2 text-sm text-white/80 transition-colors duration-200 hover:text-white"
                            >
                                @{{ thirdLevelCategory.name }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-mobile-category', {
            template: '#v-mobile-category-template',

            data() {
                return {
                    isLoading: true,
                    categories: [],
                    currentViewLevel: 'main',
                    currentSecondLevelCategory: null,
                    currentParentCategory: null
                }
            },

            mounted() {
                this.initCategories();
            },

            computed: {
                getCurrentScreenHeight() {
                    return window.innerHeight - (window.innerWidth < 920 ? 61 : 0) + 'px';
                },
            },

            methods: {
                initCategories() {
                    try {
                        const stored = localStorage.getItem('categories');

                        if (stored) {
                            const parsed = JSON.parse(stored);

                            if (Array.isArray(parsed) && parsed.length > 0) {
                                this.categories = parsed;
                                this.isLoading = false;

                                return;
                            }
                        }

                    } catch (e) {}

                    this.getCategories();
                },

                getCategories() {
                    this.$axios.get("{{ route('shop.api.categories.tree') }}")
                        .then(response => {
                            const categoryTree = response.data.data;

                            this.isLoading = false;
                            this.categories = Array.isArray(categoryTree) ? categoryTree : [];

                            localStorage.setItem('categories', JSON.stringify(this.categories));
                        })
                        .catch(error => {
                            this.isLoading = false;
                            this.categories = [];
                            console.log(error);
                        });
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

        app.component('v-mobile-drawer', {
            template: '#v-mobile-drawer-template',

            methods: {
                onDrawerClose() {
                    this.$refs.mobileCategory.currentViewLevel = 'main';
                }
            },
        });
    </script>
@endPushOnce
