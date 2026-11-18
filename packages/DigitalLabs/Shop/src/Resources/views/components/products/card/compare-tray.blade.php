{{--
    Compare tray (design concepts, renderTray): the products picked with Compare on the Swipe and Photo
    deck cards, in a bar that slides up from the bottom of the screen. Works with the real compare list
    through $cardCompare (guest localStorage or the customer's account). Rendered through the
    qubix.shop.layout.content.after hook (ShopServiceProvider), after the page content, and only when that
    content has new-style cards: their runtime (card/styled-runtime, which only the Swipe and Photo deck
    templates include) has been pushed. So never with the Classic card or on the Classic-only pages.
    Shown while the page's cards are mounted and something is picked.

    Not a dialog: no focus trap, and the page gets room at its end while the tray is open (pc-has-tray).
    Closed, it is inert; when its own Remove or Clear closes it, focus goes to a card's Compare control
    (the removed product's, else one on screen), else to the page content. Its one live region sits outside the inert part, so what the
    tray's own buttons did is read out even when Clear closes it.
--}}
@if (
    $__env->hasRenderedOnce('shop-card-styled-runtime')
    && core()->getConfigData('catalog.products.settings.compare_option')
)
    <v-card-compare-tray></v-card-compare-tray>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-card-compare-tray-template"
        >
            <div>
                <div
                    class="pc-tray"
                    :class="{ 'is-open': isOpen }"
                    role="region"
                    aria-label="@lang('shop::app.components.products.card.compare-tray')"
                    :inert="! isOpen"
                    ref="panel"
                >
                    <p class="pc-tray-head">
                        @lang('shop::app.components.products.card.compare-tray-title')

                        <small v-text="countLabel"></small>
                    </p>

                    <div class="pc-tray-slots">
                        <template
                            v-for="slot in slots"
                            :key="slot.key"
                        >
                            <button
                                type="button"
                                class="pc-tray-slot is-full"
                                :aria-label="removeLabel(slot.product)"
                                @click="remove(slot.product.id, slot.key)"
                                v-if="slot.product"
                            >
                                <img
                                    :src="slot.product.image"
                                    alt=""
                                >

                                <span
                                    class="pc-tray-x"
                                    aria-hidden="true"
                                >
                                    <x-shop::products.card.icon name="x" />
                                </span>
                            </button>

                            <span
                                class="pc-tray-slot"
                                role="img"
                                aria-label="@lang('shop::app.components.products.card.compare-tray-empty-slot')"
                                v-else
                            ></span>
                        </template>
                    </div>

                    <div class="pc-tray-actions">
                        <button
                            type="button"
                            class="pc-tray-clear"
                            ref="clear"
                            @click="clear"
                        >
                            @lang('shop::app.components.products.card.compare-tray-clear')
                        </button>

                        <a
                            class="pc-tray-go"
                            href="{{ route('shop.compare.index') }}"
                            v-text="goLabel"
                            v-if="ids.length >= 2"
                        ></a>

                        <span
                            class="pc-tray-go is-disabled"
                            v-else
                        >
                            @lang('shop::app.components.products.card.compare-tray-pick-more')
                        </span>
                    </div>
                </div>

                <span
                    class="sr-only"
                    role="status"
                >
                    <span
                        v-for="line in announcement"
                        v-text="line"
                    ></span>
                </span>
            </div>
        </script>

        <script type="module">
            const trayText = {{ Js::from([
                'count' => trans('shop::app.components.products.card.compare-tray-count'),
                'go' => trans('shop::app.components.products.card.compare-tray-go'),
                'remove' => trans('shop::app.components.products.card.compare-tray-remove'),
                'removed' => trans('shop::app.components.products.card.compare-removed'),
            ]) }};

            app.component('v-card-compare-tray', {
                template: '#v-card-compare-tray-template',

                data() {
                    return {
                        ids: [],

                        cards: 0,

                        /*
                         * What the tray's live region reads out: lines, after its own Remove and Clear.
                         */
                        announcement: [],

                        /*
                         * A Remove or Clear is on its way: a second press waits for it instead of repeating it.
                         */
                        busy: false,
                    };
                },

                computed: {
                    isOpen() {
                        return this.cards > 0 && this.ids.length > 0;
                    },

                    /**
                     * One place per allowed product, or one per pick for a list saved before the limit existed.
                     */
                    capacity() {
                        return Math.max(this.$cardCompare.limit, this.ids.length);
                    },

                    slots() {
                        return Array.from({ length: this.capacity }, (unused, index) => ({
                            key: index,
                            product: this.$cardCompare.products[this.ids[index]] ?? null,
                        }));
                    },

                    countLabel() {
                        return this.$fillPlaceholders(trayText.count, { count: this.ids.length, max: this.capacity });
                    },

                    goLabel() {
                        return this.$fillPlaceholders(trayText.go, { count: this.ids.length });
                    },
                },

                watch: {
                    isOpen() {
                        this.$nextTick(() => this.reserveSpace());
                    },
                },

                created() {
                    this.onCompareChanged = ({ ids, cards }) => {
                        this.ids = ids;

                        this.cards = cards;
                    };
                },

                mounted() {
                    this.$emitter.on('card-compare-changed', this.onCompareChanged);

                    this.onCompareChanged({ ids: [...this.$cardCompare.ids], cards: this.$cardCompare.cards });

                    // The open tray's height changes with the screen width (desktop pill, phone sheet).
                    this.resizeObserver = new ResizeObserver(() => {
                        if (this.isOpen) {
                            this.reserveSpace();
                        }
                    });

                    this.resizeObserver.observe(this.$refs.panel);
                },

                beforeUnmount() {
                    this.$emitter.off('card-compare-changed', this.onCompareChanged);

                    this.resizeObserver.disconnect();

                    document.body.classList.remove('pc-has-tray');
                },

                methods: {
                    removeLabel(product) {
                        return this.$fillPlaceholders(trayText.remove, { name: product.name });
                    },

                    async remove(productId, slotIndex) {
                        if (! await this.whileBusy(() => this.$cardCompare.remove(productId)) || this.ids.includes(productId)) {
                            return;
                        }

                        if (! this.isOpen) {
                            this.announce([trayText.removed]);

                            const controls = this.compareControls();

                            this.focusOutside(controls.find(control => control.dataset.compareProduct == productId) ?? controls.find(this.isOnScreen));

                            return;
                        }

                        this.announce([trayText.removed, this.countLabel]);

                        await this.$nextTick();

                        this.keepFocus(slotIndex);
                    },

                    async clear() {
                        if (! await this.whileBusy(() => this.$cardCompare.clear()) || this.ids.length) {
                            return;
                        }

                        this.announce([trayText.removed]);

                        const controls = this.compareControls();

                        this.focusOutside(controls.find(this.isOnScreen) ?? controls[0]);
                    },

                    /**
                     * Run one Remove or Clear at a time: false when one was already on its way.
                     */
                    async whileBusy(action) {
                        if (this.busy) {
                            return false;
                        }

                        this.busy = true;

                        try {
                            await action();
                        } finally {
                            this.busy = false;
                        }

                        return true;
                    },

                    /**
                     * The new cards' Compare controls (card/compare) that are shown, in page order.
                     */
                    compareControls() {
                        return [...document.querySelectorAll('[data-compare-product]')]
                            .filter(control => control.getBoundingClientRect().width > 0);
                    },

                    isOnScreen(control) {
                        const box = control.getBoundingClientRect();

                        return box.bottom > 0 && box.top < window.innerHeight;
                    },

                    /**
                     * The tray has closed under the pressed button: focus a card's Compare control (scrolled
                     * only as far as needed to show it), else the page content (focusable from script only).
                     */
                    focusOutside(control) {
                        if (control) {
                            control.focus({ preventScroll: true });

                            control.scrollIntoView({ block: 'nearest' });

                            return;
                        }

                        const main = document.getElementById('main');

                        if (! main) {
                            return;
                        }

                        if (! main.hasAttribute('tabindex')) {
                            main.setAttribute('tabindex', '-1');
                        }

                        main.focus({ preventScroll: true });
                    },

                    /**
                     * The pressed photo has gone: focus the photo now in its place (or the one before it), else Clear.
                     */
                    keepFocus(slotIndex) {
                        if (! this.isOpen || this.$refs.panel.contains(document.activeElement)) {
                            return;
                        }

                        const photos = this.$refs.panel.querySelectorAll('.pc-tray-slot.is-full');

                        (photos[Math.min(slotIndex, photos.length - 1)] ?? this.$refs.clear).focus();
                    },

                    /**
                     * Empty the live region first, so the same words are read out again.
                     */
                    announce(lines) {
                        this.announcement = [];

                        this.$nextTick(() => {
                            this.announcement = lines;
                        });
                    },

                    /**
                     * Room at the end of the page for the open tray, and no focus left inside a closed one.
                     */
                    reserveSpace() {
                        document.body.classList.toggle('pc-has-tray', this.isOpen);

                        document.body.style.setProperty('--pc-tray-space', this.isOpen ? `${this.$refs.panel.offsetHeight + 16}px` : '0px');

                        if (! this.isOpen && this.$refs.panel.contains(document.activeElement)) {
                            document.activeElement.blur();
                        }
                    },
                },
            });
        </script>
    @endPushOnce
@endif
