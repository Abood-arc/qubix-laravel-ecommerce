{{--
    Photo area of the "Swipe, named and sized" card (design concept G, built on A's scrub and swipe).
    Computers with a mouse: moving across the photo shows each photo in turn, and leaving shows the
    first again. Touch screens: the photos are a swipeable row (native scroll-snap). Keyboard: left
    and right arrows on the photo link. Photo 1 loads like today's card; the others load when the
    shopper shows interest (mouse over the photo, focus, touch, or a phone card scrolling into view).
    The default slot holds the overlays (badge, wishlist). Shared photo logic: the $cardGallery mixin
    (assets/js/plugins/card-gallery.js).
--}}
<v-card-swipe-gallery {{ $attributes }}>
    {{ $slot }}
</v-card-swipe-gallery>

@include('shop::components.products.card.gallery-text')

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-card-swipe-gallery-template"
    >
        <div
            class="pc-media"
            @pointerenter="onPointerEnter"
            @pointermove="onPointerMove"
            @pointerleave="onPointerLeave"
        >
            <a
                class="pc-media-link"
                :href="url"
                :aria-label="product.name"
                :aria-describedby="photos.length > 1 ? hintId : null"
                draggable="false"
                @keydown="onKeydown"
                @focus="wake()"
            >
                <div
                    class="pc-swipe-track"
                    ref="track"
                    @scroll.passive="onScroll"
                    @touchstart.passive="onTouchStart"
                >
                    <div
                        class="pc-swipe-rail"
                        ref="rail"
                    >
                        <template
                            v-for="(photo, index) in photos"
                            :key="index"
                        >
                            @include('shop::components.products.card.gallery-photo')
                        </template>
                    </div>
                </div>
            </a>

            <template v-if="photos.length > 1">
                <span
                    class="pc-ticks"
                    aria-hidden="true"
                >
                    <span
                        class="pc-tick"
                        :class="{ 'is-on': index === current }"
                        v-for="(photo, index) in photos"
                        :key="index"
                    ></span>
                </span>

                <span
                    class="pc-cap"
                    :class="{ 'is-on': current > 0 }"
                    aria-hidden="true"
                    v-text="photoLabel(current)"
                ></span>
            </template>

            @include('shop::components.products.card.gallery-hint')

            <slot></slot>
        </div>
    </script>

    <script type="module">
        /*
         * Grids whose first card has already nudged its photos once (touch screens).
         */
        const nudgedGrids = new WeakSet();

        app.component('v-card-swipe-gallery', {
            template: '#v-card-swipe-gallery-template',

            mixins: [app.config.globalProperties.$cardGallery],

            data() {
                return {
                    /*
                     * The photo on show.
                     */
                    current: 0,
                };
            },

            beforeUnmount() {
                clearTimeout(this.nudgeTimer);

                clearTimeout(this.settleTimer);
            },

            methods: {
                onReset() {
                    this.current = 0;

                    /*
                     * Not reactive: the photo the card itself is moving the row to (show()), and the
                     * photo screen readers last heard about.
                     */
                    this.target = null;

                    this.spoken = 0;

                    clearTimeout(this.settleTimer);

                    this.$refs.track?.scrollTo({ left: 0, behavior: 'instant' });
                },

                show(index, smooth) {
                    this.current = index;

                    if (index > 0) {
                        this.wake();
                    }

                    const track = this.$refs.track;

                    const left = (this.isRtl ? -1 : 1) * index * track.clientWidth;

                    this.target = Math.abs(track.scrollLeft - left) < 2 ? null : index;

                    track.scrollTo({
                        left,
                        behavior: smooth && ! this.prefersReducedMotion() ? 'smooth' : 'instant',
                    });
                },

                onKeydown(event) {
                    const step = this.arrowStep(event);

                    if (! step) {
                        return;
                    }

                    const count = this.photos.length;

                    this.show((this.current + step + count) % count, true);

                    this.announce(this.current);

                    this.spoken = this.current;
                },

                onPointerEnter(event) {
                    if (event.pointerType === 'mouse') {
                        this.wake();
                    }
                },

                onPointerMove(event) {
                    if (
                        ! this.hasMouse
                        || event.pointerType !== 'mouse'
                        || this.photos.length < 2
                        || event.target.closest('.pc-tools')
                    ) {
                        return;
                    }

                    const box = this.$el.getBoundingClientRect();

                    let fraction = (event.clientX - box.left) / box.width;

                    if (this.isRtl) {
                        fraction = 1 - fraction;
                    }

                    const index = Math.max(0, Math.min(this.photos.length - 1, Math.floor(fraction * this.photos.length)));

                    if (index !== this.current) {
                        this.show(index, false);
                    }
                },

                onPointerLeave(event) {
                    if (this.hasMouse && event.pointerType === 'mouse' && this.current !== 0) {
                        this.show(0, false);
                    }
                },

                /**
                 * A finger takes over the row, even while show() is still moving it.
                 */
                onTouchStart() {
                    this.target = null;

                    this.wake();
                },

                /**
                 * The row moved. Whatever moved it, the progress bars and the caption follow the photo
                 * on show, and screen readers hear which photo it is once a swipe settles. The card's
                 * own moves (show(): pointing, arrow keys) already set the photo, so their scrolling is
                 * only watched until it lands, which keeps a smooth arrow-key move from lighting up
                 * the bars of the photos it passes.
                 */
                onScroll() {
                    const track = this.$refs.track;

                    const index = Math.min(this.photos.length - 1, Math.round(Math.abs(track.scrollLeft) / track.clientWidth));

                    if (this.target !== null) {
                        if (Math.abs(Math.abs(track.scrollLeft) - this.target * track.clientWidth) < 2) {
                            this.target = null;
                        }

                        return;
                    }

                    if (index !== this.current) {
                        this.current = index;

                        this.wake();
                    }

                    clearTimeout(this.settleTimer);

                    this.settleTimer = setTimeout(() => this.onSwipeSettled(), 150);
                },

                onSwipeSettled() {
                    if (this.current !== this.spoken) {
                        this.spoken = this.current;

                        this.announce(this.current);
                    }
                },

                /**
                 * Touch screens: the grid's first card keeps watching until it is mostly in view, then
                 * hints once that it swipes. Every other card is done once photo 2 is on its way.
                 */
                onCardInView(entry) {
                    const grid = this.nudgeGrid();

                    if (! grid) {
                        return false;
                    }

                    if (entry.intersectionRatio <= 0.6) {
                        return true;
                    }

                    this.nudge(grid);

                    return false;
                },

                /**
                 * The grid this card should nudge in: only the first card of a grid that has not
                 * nudged yet, and never with reduced motion. Null otherwise.
                 */
                nudgeGrid() {
                    const card = this.$el.closest('.pc-card');

                    const grid = card?.parentElement;

                    if (! grid || this.prefersReducedMotion() || nudgedGrids.has(grid) || grid.firstElementChild !== card) {
                        return null;
                    }

                    return grid;
                },

                /**
                 * The first card of a grid slides a little and back, once, to hint that it swipes.
                 */
                nudge(grid) {
                    nudgedGrids.add(grid);

                    const distance = (this.isRtl ? 1 : -1) * this.$refs.track.clientWidth * 0.24;

                    this.nudgeTimer = setTimeout(() => {
                        if (this.current !== 0) {
                            return;
                        }

                        this.$refs.rail?.animate([
                            { transform: 'translateX(0)', easing: 'cubic-bezier(0.33, 0, 0.2, 1)' },
                            { transform: `translateX(${distance}px)`, offset: 0.4, easing: 'cubic-bezier(0.16, 1, 0.3, 1)' },
                            { transform: 'translateX(0)' },
                        ], { duration: 1150 });
                    }, 450);
                },
            },
        });
    </script>
@endPushOnce
