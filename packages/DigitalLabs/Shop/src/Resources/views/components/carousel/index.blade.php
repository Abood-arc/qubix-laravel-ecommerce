@props(['options'])

<v-carousel :images="{{ json_encode($options['images'] ?? []) }}">
    {{-- SSR placeholder: same aspect ratio as the real hero --}}
    <div class="aspect-[2.35/1] max-h-screen w-full overflow-hidden">
        <div class="shimmer h-full w-full"></div>
    </div>
</v-carousel>

@pushOnce('styles')
<style>
/*
 | Hero motion tuning tokens.
 | Keep current look while making future visual tweaks easy.
 */
:root {
    --vhero-enter-duration: 520ms;
    --vhero-enter-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --vhero-black-reveal-duration: 320ms;
    --vhero-start-scale: 1.2;
    --vhero-start-opacity: 0.72;
    --vhero-vignette: radial-gradient(circle at center, transparent 32%, rgba(0, 0, 0, 0.18) 100%);
    --vhero-image-saturation: 1.05;
    --vhero-image-contrast: 1.04;
}

/* ── Fast entry: slight wide start -> zoom-out to fit + quick black fade ─ */
@keyframes vhero-enter-even {
    0%   { transform: scale(var(--vhero-start-scale)) translateY(-1%); opacity: var(--vhero-start-opacity); }
    100% { transform: scale(1.0)   translateY(0%);  opacity: 1; }
}
@keyframes vhero-enter-odd {
    0%   { transform: scale(var(--vhero-start-scale)) translateY(1%); opacity: var(--vhero-start-opacity); }
    100% { transform: scale(1.0)   translateY(0%); opacity: 1; }
}

@keyframes vhero-black-reveal {
    0%   { opacity: 0.95; }
    100% { opacity: 0; }
}

.vhero-slide-active-even {
    animation: vhero-enter-even var(--vhero-enter-duration) var(--vhero-enter-ease) forwards;
    will-change: transform, opacity;
    transform-origin: center center;
}
.vhero-slide-active-odd {
    animation: vhero-enter-odd var(--vhero-enter-duration) var(--vhero-enter-ease) forwards;
    will-change: transform, opacity;
    transform-origin: center center;
}

.vhero-black-reveal {
    animation: vhero-black-reveal var(--vhero-black-reveal-duration) ease-out forwards;
    will-change: opacity;
}

.vhero-cinematic-overlay {
    background: var(--vhero-vignette);
}

.vhero-image-enhanced {
    filter: saturate(var(--vhero-image-saturation)) contrast(var(--vhero-image-contrast));
}

@media (prefers-reduced-motion: reduce) {
    .vhero-slide-active-even,
    .vhero-slide-active-odd,
    .vhero-black-reveal {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
}
</style>
@endPushOnce

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-carousel-template"
    >
        <div
            class="relative w-full overflow-hidden bg-[#f3eee7] aspect-[2.35/1] max-h-screen"
            @touchstart.passive="touchStart"
            @touchend.passive="touchEnd"
            ref="heroRef"
        >
            <!-- Shimmer shown until the first slide has loaded -->
            <div
                v-if="!isLoaded(0)"
                class="shimmer absolute inset-0 z-10"
            ></div>

            <!--
                Slides stacked via absolute positioning.
                Active slide fades in; Ken Burns + blur-reveal runs on the img.
                Parallax offset is applied via CSS variable set on scroll.
            -->
            <div
                v-for="(image, index) in images"
                :key="index"
                class="absolute inset-0 overflow-hidden"
                :style="{
                    zIndex:     index === currentIndex ? 1 : 0,
                    opacity:    index === currentIndex && isLoaded(index) ? 1 : 0,
                    transition: 'opacity 0.45s ease-out',
                    cursor:     image.link ? 'pointer' : 'default',
                }"
                @click="visitLink(image)"
            >
                <div
                    v-if="index === currentIndex"
                    :key="'black-' + index + '-' + animGen"
                    class="vhero-black-reveal pointer-events-none absolute inset-0 z-[3] bg-black"
                ></div>
                <div
                    v-if="index === currentIndex"
                    class="vhero-cinematic-overlay pointer-events-none absolute inset-0 z-[2]"
                ></div>

                <!--
                    Key changes on each activation so Vue re-mounts the img,
                    restarting the CSS animation (Ken Burns / blur-reveal reset).
                -->
                <img
                    :key="'img-' + index + (index === currentIndex ? '-a' + animGen : '-i')"
                    :class="index === currentIndex
                        ? ((animGen % 2 === 0 ? 'vhero-slide-active-even ' : 'vhero-slide-active-odd ') + 'vhero-image-enhanced')
                        : 'vhero-image-enhanced'"
                    style="height:100%; width:100%; object-fit:cover; object-position:center; user-select:none; display:block;"
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
                    :class="index === currentIndex ? 'bg-white scale-110' : 'bg-white/30'"
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
                    currentIndex:    0,
                    loadedSlides:    [],
                    autoPlayInterval: null,
                    touchStartX:     0,
                    direction:       'ltr',
                    animGen:         0,     // increments on every slide change to force img re-mount
                    parallaxOffset:  0,     // px — updated by scroll listener
                };
            },

            mounted() {
                this.direction = document.dir || 'ltr';
                setTimeout(() => this.play(), 4200);
                window.addEventListener('scroll', this.onScroll, { passive: true });
            },

            beforeUnmount() {
                clearInterval(this.autoPlayInterval);
                window.removeEventListener('scroll', this.onScroll);
            },

            methods: {
                /* ── Parallax ──────────────────────────────────────────── */
                onScroll() {
                    const el = this.$refs.heroRef;
                    if (!el) return;
                    const rect = el.getBoundingClientRect();
                    // Only apply when the hero is at least partially visible
                    if (rect.bottom < 0) return;
                    // Move background at 30% scroll speed (parallax ratio)
                    const raw = Math.max(0, -rect.top * 0.30);
                    this.parallaxOffset = Math.min(raw, 80); // cap at 80px
                },

                /* ── Slide helpers ─────────────────────────────────────── */
                isLoaded(index) {
                    return this.loadedSlides.includes(index);
                },

                onLoad(index) {
                    if (!this.isLoaded(index)) {
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
                    if (image.link) window.location.href = image.link;
                },

                /* ── Navigation ────────────────────────────────────────── */
                navigate(dir) {
                    clearInterval(this.autoPlayInterval);
                    const len = this.images.length;
                    const goForward = this.direction === 'rtl' ? dir === 'prev' : dir === 'next';
                    this.currentIndex = goForward
                        ? (this.currentIndex + 1) % len
                        : (this.currentIndex - 1 + len) % len;
                    this.animGen++;
                    this.play();
                },

                goTo(index) {
                    clearInterval(this.autoPlayInterval);
                    this.currentIndex = index;
                    this.animGen++;
                    this.play();
                },

                play() {
                    clearInterval(this.autoPlayInterval);
                    if (this.images.length < 2) return;
                    this.autoPlayInterval = setInterval(() => {
                        this.currentIndex = (this.currentIndex + 1) % this.images.length;
                        this.animGen++;
                    }, 5800);
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
