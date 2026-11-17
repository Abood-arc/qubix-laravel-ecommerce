{{--
    Photo area of the "Photo deck" card (design concept E). The photos sit in a small stack, so the
    card says "there's more" without dots. Throw the top photo sideways (drag or swipe), tap the
    "1 / 4" counter, or use the arrow keys on the photo, and it goes to the back of the pile. With a
    mouse the stack fans out a little on hover. Reduced motion: no throw and no fan; photos fade.
    The default slot holds the overlays (badge, round Add to cart). Shared photo logic: the
    $cardGallery mixin (assets/js/plugins/card-gallery.js).
--}}
<v-card-deck-gallery {{ $attributes }}>
    {{ $slot }}
</v-card-deck-gallery>

@include('shop::components.products.card.gallery-text')

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-card-deck-gallery-template"
    >
        <div
            class="pc-media pc-deck-gallery"
            :class="{ 'is-fanned': fanned }"
            @pointerenter="onPointerEnter"
            @pointerleave="fanned = false"
        >
            <a
                class="pc-media-link"
                ref="link"
                :href="url"
                :aria-label="product.name"
                :aria-describedby="photos.length > 1 ? hintId : null"
                draggable="false"
                @click="onClick"
                @keydown="onKeydown"
                @focus="wake()"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
            >
                <div class="pc-deck">
                    <div
                        class="pc-deck-layer"
                        :class="{ 'is-deep': depth(index) >= 3 }"
                        :style="{ '--d': depth(index) }"
                        :aria-hidden="depth(index) > 0 ? 'true' : null"
                        :ref="element => setLayer(index, element)"
                        v-for="(photo, index) in photos"
                        :key="index"
                    >
                        @include('shop::components.products.card.gallery-photo')
                    </div>
                </div>
            </a>

            <button
                type="button"
                class="pc-deck-next"
                :aria-label="nextLabel"
                @click="throwFront(forwardSide)"
                v-if="photos.length > 1"
            >
                <bdi
                    dir="ltr"
                    v-text="`${front + 1} / ${photos.length}`"
                ></bdi>

                <x-shop::products.card.icon name="next" />
            </button>

            @include('shop::components.products.card.gallery-hint')

            <slot></slot>
        </div>
    </script>

    <script type="module">
        const deckText = {{ Js::from([
            'nextPhoto' => trans('shop::app.components.products.card.next-photo'),
        ]) }};

        /*
         * Where a thrown photo flies to: off the side it was thrown towards, tilted.
         */
        const thrownPose = side => `translate(${side * 118}%, -6%) rotate(${side * 16}deg)`;

        app.component('v-card-deck-gallery', {
            template: '#v-card-deck-gallery-template',

            mixins: [app.config.globalProperties.$cardGallery],

            data() {
                return {
                    /*
                     * Photo indexes from the top of the pile to the bottom.
                     */
                    order: [],

                    /*
                     * A throw is running; input waits until it lands.
                     */
                    busy: false,

                    fanned: false,
                };
            },

            computed: {
                /*
                 * The photo on top of the pile.
                 */
                front() {
                    return this.order[0] ?? 0;
                },

                nextLabel() {
                    return this.$fillPlaceholders(deckText.nextPhoto, {
                        name: this.product.name,
                        current: this.front + 1,
                        total: this.photos.length,
                    });
                },

                /**
                 * "Next" throws the top photo towards the start of the line: left in English, right in Arabic.
                 */
                forwardSide() {
                    return this.isRtl ? 1 : -1;
                },
            },

            created() {
                /*
                 * Not reactive: the layer elements, the drag in progress, and the moment a drag's
                 * trailing click stops counting as a click on the product link.
                 */
                this.layers = [];

                this.drag = null;

                this.suppressClickUntil = 0;
            },

            methods: {
                onReset() {
                    this.order = this.photos.map((photo, index) => index);

                    this.busy = false;
                },

                setLayer(index, element) {
                    this.layers[index] = element;
                },

                /**
                 * 0 for the top photo, 1 for the one under it, and so on.
                 */
                depth(index) {
                    return this.order.indexOf(index);
                },

                onPointerEnter(event) {
                    if (event.pointerType !== 'mouse') {
                        return;
                    }

                    this.wake();

                    this.fanned = ! this.prefersReducedMotion();
                },

                onKeydown(event) {
                    const step = this.arrowStep(event);

                    if (! step) {
                        return;
                    }

                    step > 0 ? this.throwFront(this.forwardSide) : this.bringBack(this.forwardSide);
                },

                /**
                 * Throw the top photo off towards `side` (-1 left, 1 right); it slides back in at the bottom.
                 */
                throwFront(side) {
                    if (this.busy || this.photos.length < 2) {
                        return;
                    }

                    this.wake();

                    const front = this.layers[this.front];

                    const sendToBack = () => {
                        this.order = [...this.order.slice(1), this.order[0]];

                        this.announce(this.front);
                    };

                    if (this.prefersReducedMotion()) {
                        front.style.transform = '';

                        sendToBack();

                        this.$nextTick(() => {
                            this.layers[this.front].animate([{ opacity: 0 }, { opacity: 1 }], { duration: 180, easing: 'ease-out' });
                        });

                        return;
                    }

                    this.busy = true;

                    const start = front.style.transform || getComputedStyle(front).transform;

                    front.style.transition = 'none';

                    const out = front.animate(
                        [{ transform: start }, { transform: thrownPose(side), opacity: 0.2 }],
                        { duration: 230, easing: 'cubic-bezier(0.4, 0, 1, 1)', fill: 'forwards' }
                    );

                    out.onfinish = () => {
                        front.style.transform = '';

                        sendToBack();

                        this.$nextTick(() => {
                            out.cancel();

                            const to = getComputedStyle(front).transform;

                            front.animate(
                                [{ transform: thrownPose(side), opacity: 0 }, { transform: to, opacity: 1 }],
                                { duration: 420, easing: 'cubic-bezier(0.16, 1, 0.3, 1)' }
                            ).onfinish = () => {
                                front.style.transition = '';

                                this.busy = false;
                            };
                        });
                    };
                },

                /**
                 * The previous photo comes back from `side` onto the top of the pile.
                 */
                bringBack(side) {
                    if (this.busy || this.photos.length < 2) {
                        return;
                    }

                    this.wake();

                    const last = this.order[this.order.length - 1];

                    const back = this.layers[last];

                    back.style.transition = 'none';

                    this.order = [last, ...this.order.slice(0, -1)];

                    this.announce(this.front);

                    this.$nextTick(() => {
                        if (this.prefersReducedMotion()) {
                            back.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 180 });

                            back.style.transition = '';

                            return;
                        }

                        this.busy = true;

                        const to = getComputedStyle(back).transform;

                        back.animate(
                            [{ transform: thrownPose(side), opacity: 0 }, { transform: to, opacity: 1 }],
                            { duration: 420, easing: 'cubic-bezier(0.16, 1, 0.3, 1)' }
                        ).onfinish = () => {
                            back.style.transition = '';

                            this.busy = false;
                        };
                    });
                },

                onPointerDown(event) {
                    if (this.busy || this.photos.length < 2 || (event.pointerType === 'mouse' && event.button !== 0)) {
                        return;
                    }

                    this.drag = { id: event.pointerId, x: event.clientX, y: event.clientY, moved: false };

                    this.wake();
                },

                /**
                 * A sideways drag moves the top photo; a mostly vertical one is left to page scrolling.
                 */
                onPointerMove(event) {
                    const drag = this.drag;

                    if (! drag || drag.id !== event.pointerId) {
                        return;
                    }

                    const dx = event.clientX - drag.x;

                    const dy = event.clientY - drag.y;

                    const front = this.layers[this.front];

                    if (! drag.moved) {
                        if (Math.abs(dx) < 6 || Math.abs(dx) < Math.abs(dy)) {
                            return;
                        }

                        drag.moved = true;

                        this.$refs.link.setPointerCapture(drag.id);

                        front.style.transition = 'none';
                    }

                    if (! this.prefersReducedMotion()) {
                        front.style.transform = `translateX(${dx}px) rotate(${dx * 0.05}deg)`;
                    }
                },

                onPointerUp(event) {
                    const drag = this.drag;

                    if (! drag || drag.id !== event.pointerId) {
                        return;
                    }

                    this.drag = null;

                    if (! drag.moved) {
                        return;
                    }

                    // A drag is not a click on the product link.
                    this.suppressClickUntil = performance.now() + 350;

                    const dx = event.clientX - drag.x;

                    if (Math.abs(dx) > 48 && event.type === 'pointerup') {
                        this.throwFront(dx < 0 ? -1 : 1);

                        return;
                    }

                    const front = this.layers[this.front];

                    front.style.transition = '';

                    front.style.transform = '';
                },

                onClick(event) {
                    if (performance.now() < this.suppressClickUntil) {
                        event.preventDefault();
                    }
                },
            },
        });
    </script>
@endPushOnce
