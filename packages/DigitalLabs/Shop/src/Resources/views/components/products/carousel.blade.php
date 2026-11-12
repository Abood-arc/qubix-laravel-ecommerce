<v-products-carousel
    src="{{ $src }}"
    title="{{ $title }}"
    navigation-link="{{ $navigationLink ?? '' }}"
>
    <x-shop::shimmer.products.carousel :navigation-link="$navigationLink ?? false" />
</v-products-carousel>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-products-carousel-template"
    >
        {{-- Centred green title, a clean row, one "View all" pill; side arrows only when the row overflows on desktop. --}}
        <div
            class="container mt-16 max-lg:px-8 max-md:mt-10 max-sm:!px-4"
            v-if="! isLoading && products.length"
        >
            <h2 class="text-center font-dmserif text-[38px] leading-tight text-brand max-md:text-[28px] max-sm:text-2xl">
                @{{ title }}
            </h2>

            <div class="relative mt-8 max-md:mt-5">
                <div
                    ref="swiperContainer"
                    class="flex gap-6 pb-2.5 [&>*]:flex-[0] overflow-auto scroll-smooth scrollbar-hide max-md:gap-4 max-md:pb-0 max-md:whitespace-nowrap"
                >
                    <x-shop::products.card
                        class="min-w-[291px] max-md:h-fit max-md:min-w-56 max-sm:min-w-[192px]"
                        v-for="product in products"
                    />
                </div>

                <template v-if="products.length > 4 || (products.length > 3 && isScreenMax2xl)">
                    <button
                        type="button"
                        class="icon-arrow-left-stylish rtl:icon-arrow-right-stylish absolute top-[38%] z-[2] flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-xl text-zinc-900 shadow-[0_1px_2px_rgba(0,0,0,0.08),0_6px_16px_rgba(20,40,30,0.14)] transition-transform duration-150 active:scale-95 max-lg:hidden ltr:-left-5 rtl:-right-5"
                        aria-label="@lang('shop::app.components.products.carousel.previous')"
                        @click="swipeLeft"
                    ></button>

                    <button
                        type="button"
                        class="icon-arrow-right-stylish rtl:icon-arrow-left-stylish absolute top-[38%] z-[2] flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-xl text-zinc-900 shadow-[0_1px_2px_rgba(0,0,0,0.08),0_6px_16px_rgba(20,40,30,0.14)] transition-transform duration-150 active:scale-95 max-lg:hidden ltr:-right-5 rtl:-left-5"
                        aria-label="@lang('shop::app.components.products.carousel.next')"
                        @click="swipeRight"
                    ></button>
                </template>
            </div>

            <div
                class="mt-8 flex justify-center max-md:mt-6"
                v-if="navigationLink"
            >
                <a
                    :href="navigationLink"
                    class="primary-button !rounded-full px-10 py-3"
                    :aria-label="title"
                >
                    @lang('shop::app.components.products.carousel.view-all')
                </a>
            </div>
        </div>

        <!-- Product Card Listing -->
        <template v-if="isLoading">
            <x-shop::shimmer.products.carousel :navigation-link="$navigationLink ?? false" />
        </template>
    </script>

    <script type="module">
        app.component('v-products-carousel', {
            template: '#v-products-carousel-template',

            props: [
                'src',
                'title',
                'navigationLink',
            ],

            data() {
                return {
                    isLoading: true,

                    products: [],

                    offset: 323,

                    isScreenMax2xl: window.innerWidth <= 1440,
                };
            },

            mounted() {
                this.getProducts();
            },

            created() {
                window.addEventListener('resize', this.updateScreenSize);
            },

            beforeDestroy() {
                window.removeEventListener('resize', this.updateScreenSize);
            },

            methods: {
                getProducts() {
                    this.$axios.get(this.src)
                        .then(response => {
                            this.isLoading = false;

                            this.products = response.data.data;
                        }).catch(error => {
                            console.log(error);
                        });
                },

                updateScreenSize() {
                    this.isScreenMax2xl = window.innerWidth <= 1440;
                },

                swipeLeft() {
                    const container = this.$refs.swiperContainer;

                    const isRtl = document.documentElement.dir === 'rtl';

                    container.scrollLeft += isRtl ? this.offset : -this.offset;
                },

                swipeRight() {
                    const container = this.$refs.swiperContainer;

                    const isRtl = document.documentElement.dir === 'rtl';

                    // Check if scroll reaches the end
                    const atEnd = isRtl
                        ? Math.abs(container.scrollLeft) + container.clientWidth >= container.scrollWidth
                        : container.scrollLeft + container.clientWidth >= container.scrollWidth;

                    if (atEnd) {
                        // Reset scroll to the beginning
                        container.scrollLeft = 0;
                    } else {
                        // Scroll further in the reading direction
                        container.scrollLeft += isRtl ? -this.offset : this.offset;
                    }
                },
            },
        });
    </script>
@endPushOnce
