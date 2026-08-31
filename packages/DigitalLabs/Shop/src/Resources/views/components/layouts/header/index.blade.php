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
        class="border-b border-[#2f6f60] bg-[#1f5f4f] px-4 py-2 text-center text-xs font-semibold tracking-[0.02em] text-white/90 lg:hidden"
        v-pre
    >
        {{ core()->getConfigData('general.content.header_offer.title') }}

        <a
            href="{{ core()->getConfigData('general.content.header_offer.redirection_link') }}"
            class="underline underline-offset-2 text-white"
            role="button"
        >
            {{ core()->getConfigData('general.content.header_offer.redirection_title') }}
        </a>
    </div>
@endif

<header
    id="main-header"
    class="sticky top-0 z-10 border-b border-[#2f6f60] bg-[#1f5f4f]"
>
    <v-header-switcher>
        <!-- Desktop Header Shimmer -->
        <div class="flex flex-wrap max-lg:hidden">
            <div class="flex min-h-[78px] w-full justify-between border-b border-zinc-100 px-[60px] max-1180:px-8">
                <!-- Left Navigation Section -->
                <div class="flex items-center gap-x-10 max-[1180px]:gap-x-5">
                    <!-- Logo Shimmer -->
                    <span
                        class="shimmer block h-[29px] w-[131px] rounded"
                        role="presentation"
                    >
                    </span>

                    <!-- Categories Shimmer -->
                    <div class="flex items-center gap-5">
                        <span
                            class="shimmer h-6 w-20 rounded"
                            role="presentation"
                        >
                        </span>

                        <span
                            class="shimmer h-6 w-20 rounded"
                            role="presentation"
                        >
                        </span>

                        <span
                            class="shimmer h-6 w-20 rounded"
                            role="presentation"
                        >
                        </span>
                    </div>
                </div>

                <!-- Right Navigation Section -->
                <div class="flex items-center gap-x-9 max-[1100px]:gap-x-6 max-lg:gap-x-8">
                    <!-- Search Bar Shimmer -->
                    <div class="relative w-full max-w-[445px]">
                        <span
                            class="shimmer block h-[42px] w-[250px] rounded-lg px-11 py-3"
                            role="presentation"
                        >
                        </span>
                    </div>

                    <!-- Right Navigation Icons Shimmer -->
                    <div class="mt-1.5 flex gap-x-8 max-[1100px]:gap-x-6 max-lg:gap-x-8">
                        <!-- Compare Icon Shimmer -->
                        <span
                            class="shimmer h-6 w-6 rounded"
                            role="presentation"
                        >
                        </span>

                        <!-- Cart Icon Shimmer -->
                        <span
                            class="shimmer h-6 w-6 rounded"
                            role="presentation"
                        >
                        </span>

                        <!-- Profile Icon Shimmer -->
                        <span
                            class="shimmer h-6 w-6 rounded"
                            role="presentation"
                        >
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Header Shimmer -->
        <div class="flex flex-wrap gap-4 px-4 pb-4 pt-6 shadow-sm lg:hidden">
            <div class="flex w-full items-center justify-between">
                <!-- Left Navigation -->
                <div class="flex items-center gap-x-1.5">
                    <!-- Hamburger Menu Shimmer -->
                    <span 
                        class="shimmer block h-6 w-6 rounded" 
                        role="presentation"
                    >
                    </span>
                    
                    <!-- Logo Shimmer -->
                    <span 
                        class="shimmer block h-[29px] w-[131px] rounded" 
                        role="presentation"
                    >
                    </span>
                </div>

                <!-- Right Navigation Icons -->
                <div class="flex items-center gap-x-5 max-md:gap-x-4">
                    <!-- Compare Icon Shimmer -->
                    <span 
                        class="shimmer block h-6 w-6 rounded" 
                        role="presentation"
                    >
                    </span>
                    
                    <!-- Cart Icon Shimmer -->
                    <span 
                        class="shimmer block h-6 w-6 rounded" 
                        role="presentation"
                    >
                    </span>
                    
                    <!-- Profile Icon Shimmer -->
                    <span 
                        class="shimmer block h-6 w-6 rounded" 
                        role="presentation"
                    >
                    </span>
                </div>
            </div>

            <!-- Search Bar Shimmer -->
            <div class="flex w-full items-center">
                <div class="relative w-full">
                    <span
                        class="shimmer block h-[42px] w-full rounded-xl px-11 py-3.5 max-md:rounded-lg"
                        role="presentation"
                    >
                    </span>
                </div>
            </div>
        </div>
    </v-header-switcher>
</header>

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

            /* Per-frame polling: stable with document.scrollingElement + live header ref */
            (function loop() {
                applyScrollState();
                requestAnimationFrame(loop);
            }());

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

    <script type="module">
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
