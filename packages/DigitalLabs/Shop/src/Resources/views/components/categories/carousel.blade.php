<v-categories-carousel
    src="{{ $src }}"
    title="{{ $title }}"
    navigation-link="{{ $navigationLink ?? '' }}"
>
    <x-shop::shimmer.categories.carousel
        :count="8"
        :navigation-link="$navigationLink ?? false"
    />
</v-categories-carousel>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-categories-carousel-template"
    >
        {{--
            Photo tiles instead of small circles with labels wrapping to five lines. Up to four
            categories fill a grid (2x2 on phones, one row on desktop); more become a swipeable
            row whose next tile peeks in. Arrows show only when the row actually overflows.
            Category photos sit on a canvas tone with multiply so their white backgrounds blend in.
        --}}
        <div
            class="container mt-14 max-lg:px-8 max-md:mt-8 max-md:!px-4"
            v-if="! isLoading && categories?.length"
        >
            <div
                class="mb-6 flex items-end justify-between gap-4 max-md:mb-4"
                v-if="title || hasOverflow"
            >
                <h2
                    class="w-full text-center font-dmserif text-[38px] leading-tight text-brand max-md:text-[28px] max-sm:text-2xl"
                    v-if="title"
                    v-text="title"
                >
                </h2>

                <div
                    class="flex gap-2 ltr:ml-auto rtl:mr-auto max-lg:hidden"
                    v-if="hasOverflow"
                >
                    <button
                        type="button"
                        class="icon-arrow-left-stylish rtl:icon-arrow-right-stylish flex h-11 w-11 items-center justify-center rounded-full border border-zinc-300 text-2xl transition-colors duration-150 hover:border-navyBlue hover:bg-navyBlue hover:text-white"
                        aria-label="@lang('shop::app.components.carousel.previous')"
                        @click="swipeLeft"
                    ></button>

                    <button
                        type="button"
                        class="icon-arrow-right-stylish rtl:icon-arrow-left-stylish flex h-11 w-11 items-center justify-center rounded-full border border-zinc-300 text-2xl transition-colors duration-150 hover:border-navyBlue hover:bg-navyBlue hover:text-white"
                        aria-label="@lang('shop::app.components.carousel.next')"
                        @click="swipeRight"
                    ></button>
                </div>
            </div>

            <div
                ref="swiperContainer"
                :class="useGrid
                    ? 'grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-6'
                    : 'scrollbar-hide flex snap-x gap-3 overflow-auto scroll-smooth md:gap-6'"
            >
                <a
                    v-for="category in categories"
                    :href="`{{ route('shop.product_or_category.index', '') }}/${category.slug}`"
                    class="group block"
                    :class="useGrid ? '' : 'w-[42%] shrink-0 snap-start md:w-[calc(25%-18px)]'"
                >
                    <span class="block aspect-square overflow-hidden rounded-md bg-sand">
                        <x-shop::media.images.lazy
                            ::src="category.logo?.large_image_url || fallback"
                            ::srcset="`
                                ${(category.logo?.large_image_url || fallback)} 165w,
                                ${(category.logo?.original_image_url || category.logo?.large_image_url || fallback)} 1200w
                            `"
                            sizes="(max-width: 768px) 45vw, 300px"
                            width="300"
                            height="300"
                            class="h-full w-full object-contain mix-blend-multiply transition-transform duration-300 ease-[cubic-bezier(0.23,1,0.32,1)] 1180:group-hover:scale-105"
                            ::alt="category.name"
                        />
                    </span>

                    <span
                        class="mt-3 line-clamp-2 block text-center text-[17px] font-bold leading-6 text-brand max-sm:mt-2 max-sm:text-[15px] max-sm:leading-5"
                        v-text="category.name"
                    >
                    </span>
                </a>
            </div>
        </div>

        <!-- Category Carousel Shimmer -->
        <template v-if="isLoading">
            <x-shop::shimmer.categories.carousel
                :count="8"
                :navigation-link="$navigationLink ?? false"
            />
        </template>
    </script>

    <script type="module">
        app.component('v-categories-carousel', {
            template: '#v-categories-carousel-template',

            props: [
                'src',
                'title',
                'navigationLink',
            ],

            data() {
                return {
                    isLoading: true,

                    categories: [],

                    offset: 323,

                    hasOverflow: false,

                    fallback: "{{ qubix_asset('images/small-product-placeholder.webp') }}"
                };
            },

            computed: {
                useGrid() {
                    return this.categories.length <= 4;
                },
            },

            mounted() {
                this.getCategories();

                window.addEventListener('resize', this.measureOverflow, { passive: true });
            },

            beforeUnmount() {
                window.removeEventListener('resize', this.measureOverflow);
            },

            methods: {
                getCategories() {
                    this.$axios.get(this.src)
                        .then(response => {
                            this.isLoading = false;

                            this.categories = response.data.data;

                            this.$nextTick(this.measureOverflow);
                        }).catch(error => {
                            console.log(error);
                        });
                },

                measureOverflow() {
                    const container = this.$refs.swiperContainer;

                    this.hasOverflow = !! container && container.scrollWidth > container.clientWidth + 1;
                },

                swipeLeft() {
                    const container = this.$refs.swiperContainer;

                    const isRtl = document.documentElement.dir === 'rtl';

                    container.scrollLeft += isRtl ? this.offset : -this.offset;
                },

                swipeRight() {
                    const container = this.$refs.swiperContainer;

                    const isRtl = document.documentElement.dir === 'rtl';

                    container.scrollLeft += isRtl ? -this.offset : this.offset;
                },
            },
        });
    </script>
@endPushOnce
