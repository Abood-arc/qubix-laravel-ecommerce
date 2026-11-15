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
        {{--
            A calm grid instead of a scrolling row with arrows: up to 8 products on desktop (two rows
            of four), 6 on tablets, 4 on phones; "View all" leads to the rest.
        --}}
        <div
            class="container mt-16 max-lg:px-8 max-md:mt-10 max-sm:!px-4"
            v-if="! isLoading && products.length"
        >
            <h2 class="text-center font-dmserif text-[38px] leading-tight text-brand max-md:text-[28px] max-sm:text-2xl">
                @{{ title }}
            </h2>

            <div class="mt-8 grid grid-cols-4 justify-items-center gap-x-6 gap-y-10 max-1060:grid-cols-3 max-md:mt-5 max-md:grid-cols-2 max-md:gap-x-4 max-md:gap-y-8">
                <x-shop::products.card
                    v-for="(product, index) in products.slice(0, 8)"
                    ::class="[index >= 6 ? 'max-1060:hidden' : '', index >= 4 ? 'max-md:hidden' : '']"
                />
            </div>

            <div
                class="mt-10 flex justify-center max-md:mt-8"
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
