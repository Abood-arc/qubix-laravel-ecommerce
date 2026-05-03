{!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.before') !!}

<div class="flex min-h-[78px] w-full items-center justify-between gap-x-8 border-b border-[#2f6f60] bg-[#1f5f4f] px-[60px] max-1180:px-8">
    <!--
        Categories support first, second, and third levels.
        Additional levels can be added per project requirements.
    -->

    <!-- Left Navigation Section -->
    <div class="flex min-w-0 flex-1 items-center gap-x-8 max-[1180px]:gap-x-5">
        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.logo.before') !!}

        <a
            href="{{ route('shop.home.index') }}"
            aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.qubix')"
            class="inline-flex items-center rounded-xl bg-white px-3 py-2 shadow-[0_3px_10px_rgba(0,0,0,0.12)]"
        >
            <img
                src="{{ core()->getCurrentChannel()->logo_url ?? qubix_asset('images/logo.svg') }}"
                width="131"
                height="29"
                class="h-auto max-h-[30px] w-auto"
                alt="{{ config('app.name') }}"
            >
        </a>

        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.logo.after') !!}

        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.category.before') !!}

        <v-desktop-category class="min-w-0 flex-1">
            <div class="flex items-center gap-5">
                <span class="w-20 h-6 rounded shimmer" role="presentation"></span>
                <span class="w-20 h-6 rounded shimmer" role="presentation"></span>
                <span class="w-20 h-6 rounded shimmer" role="presentation"></span>
            </div>
        </v-desktop-category>

        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.category.after') !!}
    </div>

    <!-- Right Navigation Section -->
    <div class="flex items-center gap-x-9 max-[1100px]:gap-x-6 max-lg:gap-x-8">

        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.search_bar.before') !!}

        <!-- Search Bar -->
        <details class="header-search group relative">
            <summary
                class="inline-flex h-10 w-10 list-none cursor-pointer items-center justify-center rounded-full text-white transition-colors hover:bg-white/10"
                aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.search')"
            >
                <span class="icon-search text-2xl group-open:hidden"></span>
                <span class="icon-cancel text-2xl hidden group-open:inline-block"></span>
            </summary>

            <div class="absolute right-0 top-full z-[70] mt-3 w-[min(92vw,980px)] rounded-2xl border border-white/25 bg-[#1f5f4f] p-3 shadow-[0_14px_32px_rgba(10,30,24,0.35)]">
                <form
                    action="{{ route('shop.search.index') }}"
                    class="flex items-center"
                    role="search"
                >
                    <label
                        for="organic-search"
                        class="sr-only"
                    >
                        @lang('shop::app.components.layouts.header.desktop.bottom.search')
                    </label>

                    <div class="icon-search pointer-events-none absolute top-[1.12rem] text-xl text-white ltr:left-6 rtl:right-6"></div>

                    <input
                        id="organic-search"
                        type="text"
                        name="query"
                        value="{{ request('query') }}"
                        class="block w-full rounded-xl border border-white/30 bg-white/10 px-12 py-3 text-sm font-medium text-white placeholder:text-white/70 transition-all hover:border-white/50 focus:border-white"
                        minlength="{{ core()->getConfigData('catalog.products.search.min_query_length') }}"
                        maxlength="{{ core()->getConfigData('catalog.products.search.max_query_length') }}"
                        placeholder="@lang('shop::app.components.layouts.header.desktop.bottom.search-text')"
                        aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.search-text')"
                        aria-required="true"
                        pattern="[^\\]+"
                        required
                    >

                    <button
                        type="submit"
                        class="hidden"
                        aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.submit')"
                    >
                    </button>

                </form>
            </div>
        </details>

        {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.search_bar.after') !!}

        <!-- Right Navigation Icons -->
        <div class="mt-1.5 flex gap-x-8 max-[1100px]:gap-x-6 max-lg:gap-x-8">

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.compare.before') !!}

            <!-- Compare -->
            @if(core()->getConfigData('catalog.products.settings.compare_option'))
                <a
                    href="{{ route('shop.compare.index') }}"
                    aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.compare')"
                >
                    <span
                        class="inline-block text-2xl cursor-pointer text-white icon-compare"
                        role="presentation"
                    ></span>
                </a>
            @endif

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.compare.after') !!}

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.mini_cart.before') !!}

            <!-- Mini cart -->
            @if(core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                @include('shop::checkout.cart.mini-cart')
            @endif

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.mini_cart.after') !!}

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.profile.before') !!}

            <!-- User profile -->
            <x-shop::dropdown position="bottom-{{ core()->getCurrentLocale()->direction === 'ltr' ? 'right' : 'left' }}">
                <x-slot:toggle>
                    <span
                        class="inline-block text-2xl cursor-pointer text-white icon-users"
                        role="button"
                        aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.profile')"
                        tabindex="0"
                    ></span>
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

                            @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                                <a
                                    class="px-5 py-2 text-base cursor-pointer hover:bg-zinc-50"
                                    href="{{ route('shop.customers.account.wishlist.index') }}"
                                >
                                    @lang('shop::app.components.layouts.header.desktop.bottom.wishlist')
                                </a>
                            @endif

                            @auth('customer')
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
                            @endauth

                            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.profile_dropdown.links.after') !!}
                        </div>
                    </x-slot>
                @endauth
            </x-shop::dropdown>

            {!! view_render_event('qubix.shop.components.layouts.header.desktop.bottom.profile.after') !!}
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-desktop-category-template"
    >
        <!-- Loading State -->
        <div
            class="flex items-center justify-center gap-5"
            v-if="isLoading"
        >
            <span class="w-20 h-6 rounded shimmer" role="presentation"></span>
            <span class="w-20 h-6 rounded shimmer" role="presentation"></span>
            <span class="w-20 h-6 rounded shimmer" role="presentation"></span>
        </div>

        <!-- Default category mega-menu layout -->
        <div
            class="velocity-nav-category-list flex flex-1 flex-wrap items-center justify-center gap-x-1 gap-y-1"
            v-else-if="'{{ core()->getConfigData('general.design.categories.category_view') }}' !== 'sidebar'"
        >
            <div
                class="velocity-nav-category-group group relative flex h-[77px] items-center border-b-2 border-transparent pb-0.5 transition-[border-color] duration-200 hover:border-white/80"
                v-for="category in categories"
            >
                <span class="flex h-full items-center">
                    <a
                        :href="category.url"
                        class="velocity-nav-category-trigger inline-flex max-w-[12rem] items-center justify-center rounded-full px-4 py-2.5 text-center font-poppins text-[15px] font-semibold tracking-normal text-white ring-1 ring-transparent transition-[background-color,box-shadow,color,ring-color] duration-200 hover:bg-white/10 hover:text-white hover:ring-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40 sm:max-w-none sm:text-[15px]"
                    >
                        @{{ category.name }}
                    </a>
                </span>

                <div
                    class="velocity-nav-megamenu pointer-events-none absolute top-full z-[60] max-h-[min(580px,80vh)] w-[min(94vw,1260px)] max-w-[1260px] translate-y-1 overflow-y-auto overflow-x-auto rounded-b-2xl border border-white/10 bg-[#1f5f4f] px-8 py-8 opacity-0 shadow-[0_24px_50px_-12px_rgba(10,30,24,0.45)] backdrop-blur-md transition duration-300 ease-out group-hover:pointer-events-auto group-hover:translate-y-0 group-hover:opacity-100 group-hover:duration-200 group-hover:ease-in ltr:left-1/2 ltr:-translate-x-1/2 rtl:right-1/2 rtl:translate-x-1/2"
                    v-if="category.children && category.children.length"
                >
                    <div class="flex flex-wrap justify-start gap-x-12 gap-y-10 xl:gap-x-16">
                        <div
                            class="velocity-nav-megamenu-col grid min-w-[140px] max-w-[180px] flex-1 grid-cols-1 content-start gap-y-8"
                            v-for="pairCategoryChildren in pairCategoryChildren(category)"
                        >
                            <template v-for="secondLevelCategory in pairCategoryChildren">
                                <div>
                                    <p class="velocity-nav-megamenu-title font-semibold text-white">
                                        <a
                                            class="inline-block pb-1 transition-colors hover:text-white/80"
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
                                                class="velocity-nav-megalnk block rounded-lg px-2 py-1.5 text-[13px] font-medium text-white/70 transition-colors hover:bg-white/10 hover:text-white"
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
                    class="flex h-[77px] cursor-pointer items-center border-b-4 border-transparent hover:border-navyBlue"
                    @click="toggleCategoryDrawer"
                >
                    <span class="flex items-center gap-1 px-5 uppercase">
                        <span class="text-xl icon-hamburger"></span>
                        @lang('shop::app.components.layouts.header.desktop.bottom.all')
                    </span>
                </div>

                <!-- First 4 categories in main nav -->
                <div
                    class="group relative flex h-[77px] items-center border-b-4 border-transparent hover:border-navyBlue"
                    v-for="category in categories.slice(0, 4)"
                >
                    <span>
                        <a
                            :href="category.url"
                            class="inline-block px-5 uppercase"
                        >
                            @{{ category.name }}
                        </a>
                    </span>

                    <!-- Category dropdown -->
                    <div
                        class="pointer-events-none absolute top-[78px] z-[1] max-h-[580px] w-max max-w-[1260px] translate-y-1 overflow-auto rounded-b-xl border-t border-zinc-100 bg-white p-9 opacity-0 shadow-[0_6px_20px_rgba(6,12,59,0.12)] transition duration-300 ease-out group-hover:pointer-events-auto group-hover:translate-y-0 group-hover:opacity-100 group-hover:duration-200 group-hover:ease-in ltr:-left-9 rtl:-right-9"
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
                    try {
                        const stored = localStorage.getItem('categories');

                        if (stored) {
                            this.categories = JSON.parse(stored);
                            this.isLoading = false;

                            return;
                        }

                    } catch (e) {}

                    this.getCategories();
                },

                getCategories() {
                    this.$axios.get("{{ route('shop.api.categories.tree') }}")
                        .then(response => {
                            this.isLoading = false;
                            this.categories = response.data.data;
                            localStorage.setItem('categories', JSON.stringify(this.categories));
                        })
                        .catch(error => {
                            console.log(error);
                        });
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
