@php
    $showCompare = (bool) core()->getConfigData('catalog.products.settings.compare_option');
    $showWishlist = (bool) core()->getConfigData('customer.settings.wishlist.wishlist_option');
@endphp

{{--
    Phone header: menu | logo (centred) | search and cart. Account and compare live in the
    menu drawer, so the bar stays at three calm controls.
--}}
<div class="w-full lg:hidden">
    <div class="grid h-[60px] w-full grid-cols-[1fr_auto_1fr] items-center px-1.5 text-white">
        <div class="flex items-center">
            {!! view_render_event('qubix.shop.components.layouts.header.mobile.drawer.before') !!}

            <v-mobile-drawer></v-mobile-drawer>

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.drawer.after') !!}
        </div>

        <div class="flex items-center">
            {!! view_render_event('qubix.shop.components.layouts.header.mobile.logo.before') !!}

            <a
                href="{{ route('shop.home.index') }}"
                class="inline-flex items-center"
                aria-label="{{ config('app.name') }}"
            >
                <img
                    src="{{ core()->getCurrentChannel()->logo_url ?? qubix_asset('images/logo.svg') }}"
                    alt="{{ config('app.name') }}"
                    class="h-10 w-auto object-contain"
                >
            </a>

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.logo.after') !!}
        </div>

        <div class="flex items-center justify-end">
            {!! view_render_event('qubix.shop.components.layouts.header.mobile.search.before') !!}

            {{--
                Search opens a full-screen sheet (fixed, over the page) with one large field and a
                close button; visibility follows native `details[open]` via app.css.
            --}}
            <details
                class="header-search mobile-header-details-search"
                @toggle="$event.target.open && $event.target.querySelector('input[type=search]').focus()"
            >
                <summary
                    class="inline-flex h-11 w-11 list-none cursor-pointer items-center justify-center rounded-full text-white transition-colors hover:bg-white/10"
                    aria-label="@lang('shop::app.components.layouts.header.mobile.search')"
                >
                    <span class="icon-search text-2xl"></span>
                </summary>

                <div class="mobile-search-panel fixed inset-0 z-[90] hidden flex-col bg-white">
                    <div class="flex h-[60px] items-center gap-1 bg-brand ltr:pl-3 ltr:pr-1 rtl:pl-1 rtl:pr-3">
                        <form
                            action="{{ route('shop.search.index') }}"
                            class="relative flex flex-1 items-center text-zinc-700 [&_.icon-camera]:text-zinc-600 [&_label.icon-camera]:text-zinc-600"
                            role="search"
                        >
                            <label
                                for="shop-header-search-mobile-panel"
                                class="sr-only"
                            >
                                @lang('shop::app.components.layouts.header.mobile.search')
                            </label>

                            <div class="icon-search pointer-events-none absolute top-1/2 -translate-y-1/2 text-xl text-zinc-500 ltr:left-4 rtl:right-4"></div>

                            <input
                                id="shop-header-search-mobile-panel"
                                type="search"
                                name="query"
                                value="{{ request('query') }}"
                                class="block h-11 w-full rounded-full border-0 bg-white px-11 text-base text-zinc-900 placeholder:text-zinc-500 focus:outline-none"
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

                        <button
                            type="button"
                            class="icon-cancel inline-flex h-11 w-11 shrink-0 items-center justify-center text-2xl text-white"
                            aria-label="@lang('shop::app.components.layouts.header.mobile.search')"
                            @click="$event.currentTarget.closest('details').open = false"
                        ></button>
                    </div>
                </div>
            </details>

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.search.after') !!}

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.mini_cart.before') !!}

            @if(core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                @include('shop::checkout.cart.mini-cart')
            @endif

            {!! view_render_event('qubix.shop.components.layouts.header.mobile.mini_cart.after') !!}
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-mobile-drawer-template">
        <x-shop::drawer
            position="left"
            width="100%"
            panel-class="shop-mobile-nav-drawer bg-white"
            @close="onDrawerClose"
        >
            <x-slot:toggle>
                <span
                    class="inline-flex h-11 w-11 cursor-pointer items-center justify-center text-2xl text-white icon-hamburger"
                    role="button"
                    tabindex="0"
                    aria-label="@lang('shop::app.components.layouts.header.mobile.menu')"
                ></span>
            </x-slot>

            <x-slot:header class="!border-brand bg-brand text-white [&_.icon-cancel]:text-white">
                <div class="flex items-center justify-between">
                    <a href="{{ route('shop.home.index') }}">
                        <img
                            src="{{ core()->getCurrentChannel()->logo_url ?? qubix_asset('images/logo.svg') }}"
                            alt="{{ config('app.name') }}"
                            class="h-10 w-auto object-contain"
                        >
                    </a>
                </div>
            </x-slot>

            <x-slot:content class="!p-0 shop-mobile-nav-drawer-content">
                <!-- Account Profile Section -->
                <div class="border-b border-zinc-200 p-4">
                    <div class="grid grid-cols-[auto_1fr] items-center gap-4 rounded-xl bg-sage p-3">
                        <div>
                            <img
                                src="{{ auth()->user()?->image_url ?? qubix_asset('images/user-placeholder.png') }}"
                                class="h-12 w-12 rounded-full"
                            >
                        </div>

                        @guest('customer')
                            <a
                                href="{{ route('shop.customer.session.create') }}"
                                class="flex items-center text-base font-semibold text-brand"
                            >
                                @lang('shop::app.components.layouts.header.mobile.login')

                                <i class="icon-double-arrow text-xl ltr:ml-2 rtl:mr-2"></i>
                            </a>
                        @endguest

                        @auth('customer')
                            <a
                                href="{{ route('shop.customers.account.index') }}"
                                class="flex min-w-0 flex-col"
                                v-pre
                            >
                                <span class="break-words text-lg font-bold text-zinc-900">Hello! {{ auth()->user()?->first_name }}</span>

                                <span class="truncate text-sm text-zinc-500">{{ auth()->user()?->email }}</span>
                            </a>
                        @endauth
                    </div>
                </div>

                {!! view_render_event('qubix.shop.components.layouts.header.mobile.drawer.categories.before') !!}

                <v-mobile-category ref="mobileCategory"></v-mobile-category>

                {!! view_render_event('qubix.shop.components.layouts.header.mobile.drawer.categories.after') !!}

                @php
                    $drawerWhatsappDigits = preg_replace('/\D+/', '', (string) core()->getConfigData('general.content.store_contact.whatsapp_number'));
                    $drawerContactEmail = core()->getConfigData('general.content.store_contact.email');
                @endphp

                {{-- Ways to reach the store; WhatsApp and email appear once set in admin (Store Contact). --}}
                <div class="mx-6 grid border-t border-zinc-200 py-3">
                    <a
                        href="{{ route('shop.home.contact_us') }}"
                        class="flex min-h-12 items-center text-base font-semibold text-brand"
                    >
                        @lang('shop::app.components.layouts.footer.corporate-enquiry')
                    </a>

                    @if ($showCompare)
                        <a
                            href="{{ route('shop.compare.index') }}"
                            class="flex min-h-12 items-center text-base font-semibold text-brand"
                        >
                            @lang('shop::app.components.layouts.header.mobile.compare')
                        </a>
                    @endif

                    @if ($drawerWhatsappDigits)
                        <a
                            href="https://wa.me/{{ $drawerWhatsappDigits }}"
                            class="flex min-h-12 items-center text-base font-semibold text-brand"
                            target="_blank"
                            rel="noopener"
                        >
                            @lang('shop::app.components.layouts.footer.whatsapp')
                        </a>
                    @endif

                    @if ($drawerContactEmail)
                        <a
                            href="mailto:{{ $drawerContactEmail }}"
                            class="flex min-h-12 items-center text-base font-semibold text-brand"
                            v-pre
                        >
                            {{ $drawerContactEmail }}
                        </a>
                    @endif
                </div>
            </x-slot>

            <x-slot:footer>
                <!-- Locale & Currency -->
                @if(core()->getCurrentChannel()->locales()->count() > 1 || core()->getCurrentChannel()->currencies()->count() > 1)
                    <div class="fixed bottom-0 z-10 grid w-full max-w-full grid-cols-[1fr_auto_1fr] items-center justify-items-center border-t border-brand/10 bg-sage px-5 text-brand ltr:left-0 rtl:right-0">
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

                        <span class="h-5 w-0.5 bg-brand/20"></span>

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
        {{-- Sized to its content (not h-full) so the store links below the list stay on screen. --}}
        <div class="relative overflow-hidden">
            <div
                class="flex transition-transform duration-300"
                :class="{
                    'ltr:translate-x-0 rtl:translate-x-0': currentViewLevel !== 'third',
                    'ltr:-translate-x-full rtl:translate-x-full': currentViewLevel === 'third'
                }"
            >
                <!-- First level -->
                <div class="flex-shrink-0 w-full h-full px-6 overflow-auto">
                    <div class="py-2">
                        <div
                            v-for="category in categories"
                            :key="category.id"
                            class="border-b border-zinc-200 last:border-b-0"
                            :class="{'pb-2': category.children && category.children.length}"
                        >
                            <div class="flex items-center justify-between transition-colors duration-200 cursor-pointer">
                                <a :href="category.url" class="flex min-h-12 flex-1 items-center text-base font-bold text-zinc-900 hover:text-brand">
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
                                        <a :href="secondLevelCategory.url" class="flex min-h-11 items-center text-base text-zinc-700 hover:text-brand ltr:pl-3 rtl:pr-3">
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
                    <div class="border-b border-zinc-200 px-6 py-4">
                        <button
                            @click="goBackToMainView"
                            class="flex min-h-11 items-center justify-center gap-2 text-zinc-900 focus:outline-none"
                            aria-label="Go back"
                        >
                            <span class="text-lg icon-arrow-left rtl:icon-arrow-right"></span>
                            <div class="text-base font-semibold">
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
                                class="flex min-h-11 items-center text-base text-zinc-700 transition-colors duration-200 hover:text-brand"
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
