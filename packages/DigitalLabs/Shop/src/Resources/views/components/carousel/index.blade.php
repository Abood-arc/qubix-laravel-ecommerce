@props(['options'])

<v-carousel :images="{{ json_encode($options['images'] ?? []) }}">
    {{-- SSR placeholder: same aspect ratio as the real hero --}}
    <div class="aspect-[2.743/1] max-h-screen w-full overflow-hidden">
        <div class="shimmer h-full w-full"></div>
    </div>
</v-carousel>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-carousel-template"
    >
        <div
            class="relative w-full overflow-hidden bg-[#f3eee7] aspect-[2.743/1] max-h-screen"
            @touchstart.passive="touchStart"
            @touchend.passive="touchEnd"
        >
            <!-- Shimmer shown until the first slide has loaded -->
            <div
                v-if="!isLoaded(0)"
                class="shimmer absolute inset-0 z-0"
            ></div>

            <!--
                All slides are stacked via absolute inset-0.
                Only the active slide is opacity-100; the rest are opacity-0.
                CSS transition on opacity produces the crossfade.
            -->
            <div
                v-for="(image, index) in images"
                :key="index"
                class="absolute inset-0"
                :style="{
                    zIndex: index === currentIndex ? 1 : 0,
                    opacity: index === currentIndex && isLoaded(index) ? 1 : 0,
                    transition: 'opacity 0.7s ease-in-out',
                    cursor: image.link ? 'pointer' : 'default',
                }"
                @click="visitLink(image)"
            >
                <img
                    class="h-full w-full select-none object-cover object-center"
                    :src="image.image"
                    :srcset="buildSrcset(image)"
                    sizes="(max-width: 525px) 525px, (max-width: 1024px) 1024px, (max-width: 1600px) 1280px, 1920px"
                    :alt="image.title || 'Hero image ' + (index + 1)"
                    :fetchpriority="index === 0 ? 'high' : 'low'"
                    :loading="index === 0 ? 'eager' : 'lazy'"
                    :decoding="index === 0 ? 'sync' : 'async'"
                    draggable="false"
                    @load="onLoad(index)"
                />
            </div>

            <!-- Prev arrow -->
            <button
                v-if="images.length >= 2"
                class="icon-arrow-left absolute left-4 top-1/2 z-[2] -translate-y-1/2 hidden rounded-full border border-white/70 bg-white/80 p-3 text-2xl font-bold text-zinc-900 opacity-80 shadow-md backdrop-blur transition-all hover:bg-white hover:opacity-100 focus:outline-none md:inline-block"
                aria-label="@lang('shop::components.carousel.previous')"
                @click="navigate('prev')"
            ></button>

            <!-- Next arrow -->
            <button
                v-if="images.length >= 2"
                class="icon-arrow-right absolute right-4 top-1/2 z-[2] -translate-y-1/2 hidden rounded-full border border-white/70 bg-white/80 p-3 text-2xl font-bold text-zinc-900 opacity-80 shadow-md backdrop-blur transition-all hover:bg-white hover:opacity-100 focus:outline-none md:inline-block"
                aria-label="@lang('shop::components.carousel.next')"
                @click="navigate('next')"
            ></button>

            <!-- Pagination dots -->
            <div
                v-if="images.length >= 2"
                class="absolute bottom-5 left-0 z-[2] flex w-full justify-center max-md:bottom-3.5 max-sm:bottom-2.5"
            >
                <button
                    v-for="(image, index) in images"
                    :key="'dot-' + index"
                    class="mx-1 h-3 w-3 rounded-full border border-white/50 transition-colors focus:outline-none max-md:h-2 max-md:w-2 max-sm:h-1.5 max-sm:w-1.5"
                    :class="index === currentIndex ? 'bg-white' : 'bg-white/20'"
                    :aria-label="'Go to slide ' + (index + 1)"
                    @click="goTo(index)"
                ></button>
            </div>
        </div>
    </script>

    <script type="module">
        app.component("v-carousel", {
            template: '#v-carousel-template',

            props: ['images'],

            data() {
                return {
                    currentIndex: 0,
                    loadedSlides: [],
                    autoPlayInterval: null,
                    touchStartX: 0,
                    direction: 'ltr',
                };
            },

            mounted() {
                this.direction = document.dir || 'ltr';

                // Delay autoplay so first image has time to settle
                setTimeout(() => this.play(), 4000);
            },

            beforeUnmount() {
                clearInterval(this.autoPlayInterval);
            },

            methods: {
                isLoaded(index) {
                    return this.loadedSlides.includes(index);
                },

                onLoad(index) {
                    if (! this.isLoaded(index)) {
                        this.loadedSlides.push(index);
                    }
                },

                buildSrcset(image) {
                    const src = image.image;

                    return [
                        `${src} 1920w`,
                        `${src.replace('storage', 'cache/large')} 1280w`,
                        `${src.replace('storage', 'cache/medium')} 1024w`,
                        `${src.replace('storage', 'cache/small')} 525w`,
                    ].join(', ');
                },

                visitLink(image) {
                    if (image.link) {
                        window.location.href = image.link;
                    }
                },

                navigate(dir) {
                    clearInterval(this.autoPlayInterval);

                    const len = this.images.length;
                    const goForward = this.direction === 'rtl' ? dir === 'prev' : dir === 'next';

                    this.currentIndex = goForward
                        ? (this.currentIndex + 1) % len
                        : (this.currentIndex - 1 + len) % len;

                    this.play();
                },

                goTo(index) {
                    clearInterval(this.autoPlayInterval);
                    this.currentIndex = index;
                    this.play();
                },

                play() {
                    clearInterval(this.autoPlayInterval);

                    if (this.images.length < 2) {
                        return;
                    }

                    this.autoPlayInterval = setInterval(() => {
                        this.currentIndex = (this.currentIndex + 1) % this.images.length;
                    }, 5000);
                },

                touchStart(e) {
                    this.touchStartX = e.touches[0].clientX;
                },

                touchEnd(e) {
                    const delta = this.touchStartX - e.changedTouches[0].clientX;

                    if (Math.abs(delta) > 50) {
                        this.navigate(delta > 0 ? 'next' : 'prev');
                    }
                },
            },
        });
    </script>
@endPushOnce
