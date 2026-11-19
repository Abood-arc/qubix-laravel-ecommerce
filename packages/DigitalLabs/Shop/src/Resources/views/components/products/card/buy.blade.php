{{--
    Add to cart on the Swipe and Photo deck cards. variant: 'full' (a full-width button under the price,
    Swipe card) or 'round' (a round bag button on the photo, Photo deck card). The button shows "Adding"
    dots, then a tick, then becomes a − 1 + control while the product is in the cart (design concepts:
    buyHTML, miniBuyHTML, setQty). Everything goes through $cardCart (card/styled-runtime): the Classic
    card's add call, then the cart page's update call (same stock rules), and the line is removed at 0.
    A product with nothing to choose skips the dots and the tick: it shows the control at once.
    What changed is read out through the card's one live region ($cardAnnounce, card/gallery-hint).
--}}
@props(['variant'])

@if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
    {!! view_render_event('qubix.shop.components.products.card.add_to_cart.before') !!}

    @include('shop::components.products.card.styled-runtime')

    <v-card-buy
        {{ $attributes }}
        variant="{{ $variant }}"
    ></v-card-buy>

    {!! view_render_event('qubix.shop.components.products.card.add_to_cart.after') !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-card-buy-template"
        >
            <div
                class="pc-buy"
                :class="`pc-buy--${variant}`"
                :data-state="state"
            >
                <button
                    type="button"
                    class="pc-add"
                    ref="button"
                    :disabled="! product.is_saleable"
                    :aria-disabled="state !== 'idle' ? 'true' : 'false'"
                    :tabindex="state === 'idle' ? null : -1"
                    :aria-label="variant === 'round' ? label('quickAdd') : null"
                    @click="add"
                >
                    <template v-if="variant === 'round'">
                        <span class="pc-add-idle">
                            <x-shop::products.card.icon name="bag" />

                            <span
                                class="pc-add-plus"
                                aria-hidden="true"
                            >
                                <x-shop::products.card.icon name="plus" />
                            </span>
                        </span>

                        <span
                            class="pc-add-wait"
                            aria-hidden="true"
                        >
                            <span class="pc-dots"><i></i><i></i><i></i></span>
                        </span>

                        <span
                            class="pc-add-done"
                            aria-hidden="true"
                        >
                            <x-shop::products.card.icon
                                name="check"
                                class="pc-tickmark"
                            />
                        </span>
                    </template>

                    <template v-else>
                        <span
                            class="pc-add-idle"
                            v-if="product.is_saleable"
                        >
                            <x-shop::products.card.icon name="bag" />

                            @lang('shop::app.components.products.card.add-to-cart')

                            <span
                                class="sr-only"
                                v-text="product.name"
                            ></span>
                        </span>

                        <span
                            class="pc-add-idle"
                            v-else
                        >
                            @lang('shop::app.components.products.card.out-of-stock')
                        </span>

                        <span
                            class="pc-add-wait"
                            aria-hidden="true"
                        >
                            @lang('shop::app.components.products.card.adding')

                            <span class="pc-dots"><i></i><i></i><i></i></span>
                        </span>

                        <span
                            class="pc-add-done"
                            aria-hidden="true"
                        >
                            <x-shop::products.card.icon
                                name="check"
                                class="pc-tickmark"
                            />

                            @lang('shop::app.components.products.card.added')
                        </span>
                    </template>
                </button>

                <div
                    class="pc-stepper"
                    role="group"
                    :aria-label="label('cartQuantity', { count: quantity })"
                >
                    <button
                        type="button"
                        :aria-label="label('removeOne')"
                        :tabindex="state === 'qty' ? null : -1"
                        @click="step(-1)"
                    >
                        <x-shop::products.card.icon name="minus" />
                    </button>

                    <span class="pc-qty">
                        <b
                            :key="quantity"
                            v-text="quantity"
                        ></b>

                        <span class="pc-qty-word"> @lang('shop::app.components.products.card.in-cart')</span>
                    </span>

                    <button
                        type="button"
                        ref="increase"
                        :aria-label="label('addOne')"
                        :tabindex="state === 'qty' ? null : -1"
                        @click="step(1)"
                    >
                        <x-shop::products.card.icon name="plus" />
                    </button>
                </div>
            </div>
        </script>

        <script type="module">
            const buyText = {{ Js::from([
                'addOne' => trans('shop::app.components.products.card.add-one'),
                'added' => trans('shop::app.components.products.card.added'),
                'cartQuantity' => trans('shop::app.components.products.card.cart-quantity'),
                'quickAdd' => trans('shop::app.components.products.card.quick-add'),
                'removed' => trans('shop::app.checkout.cart.success-remove'),
                'removeOne' => trans('shop::app.components.products.card.remove-one'),
            ]) }};

            app.component('v-card-buy', {
                template: '#v-card-buy-template',

                props: {
                    product: {
                        type: Object,
                        required: true,
                    },

                    variant: {
                        type: String,
                        required: true,
                    },
                },

                data() {
                    return {
                        /*
                         * idle, or loading / added while Add to cart is at work.
                         */
                        phase: 'idle',

                        /*
                         * How many the cart holds (or is about to hold), from $cardCart.
                         */
                        quantity: 0,
                    };
                },

                computed: {
                    /**
                     * idle, loading, added, or qty (in the cart: the − 1 + control shows).
                     */
                    state() {
                        if (this.phase !== 'idle') {
                            return this.phase;
                        }

                        return this.quantity > 0 ? 'qty' : 'idle';
                    },
                },

                watch: {
                    /*
                     * Grids reuse card components when their product list changes (filters, paging).
                     */
                    'product.id'() {
                        clearTimeout(this.addedTimer);

                        this.phase = 'idle';

                        this.sync();
                    },

                    /*
                     * Keep the keyboard on the card when the button and the − 1 + control swap places:
                     * on "+" once the control shows, back on Add to cart once the line is gone.
                     */
                    state(now) {
                        if (! this.$el.contains(document.activeElement) || ! ['idle', 'qty'].includes(now)) {
                            return;
                        }

                        this.$nextTick(() => (now === 'qty' ? this.$refs.increase : this.$refs.button).focus({ preventScroll: true }));
                    },
                },

                created() {
                    this.onCartChanged = () => this.sync();
                },

                mounted() {
                    this.$emitter.on('card-cart-changed', this.onCartChanged);

                    this.$cardCart.load();

                    this.sync();
                },

                beforeUnmount() {
                    this.$emitter.off('card-cart-changed', this.onCartChanged);

                    clearTimeout(this.addedTimer);
                },

                methods: {
                    label(key, replacements = {}) {
                        return this.$fillPlaceholders(buyText[key], { name: this.product.name, ...replacements });
                    },

                    sync() {
                        this.quantity = this.$cardCart.quantity(this.product.id);
                    },

                    /**
                     * A product with nothing to choose turns into the − 1 + control at once, and its photo
                     * flies to the header cart as it is tapped; $cartWrites sends the add, and if the cart
                     * refuses, the card goes back to Add to cart and reads out why. Any other product shows
                     * the "Adding" dots until the cart answers, then the tick.
                     */
                    async add() {
                        if (this.state !== 'idle') {
                            return;
                        }

                        if (this.$cartWrites.isInstant(this.product)) {
                            const added = this.$cardCart.add(this.product);

                            this.$cardAnnounce(this.$el, buyText.added);

                            this.flyToCart();

                            const { message } = await added;

                            if (message) {
                                this.$cardAnnounce(this.$el, message);
                            }

                            return;
                        }

                        this.phase = 'loading';

                        if (! (await this.$cardCart.add(this.product)).added) {
                            this.phase = 'idle';

                            return;
                        }

                        this.phase = 'added';

                        this.$cardAnnounce(this.$el, buyText.added);

                        this.addedTimer = setTimeout(() => (this.phase = 'idle'), 900);

                        this.flyToCart();
                    },

                    /**
                     * Last, and on its own: the flight is decoration, whatever it does cannot touch the add.
                     */
                    flyToCart() {
                        this.$cardFlyToCart(this.$refs.button, this.product.base_image?.small_image_url);
                    },

                    /**
                     * − or +. The control shows the new quantity at once; $cardCart sends it when the
                     * writes before it are done, and rolls back to the cart's own if the cart refuses.
                     * The outcome is read out: the new quantity, or why the cart said no (else the
                     * quantity it went back to). A change that a quicker click took over says nothing.
                     */
                    async step(change) {
                        const { outcome, message } = await this.$cardCart.setQuantity(this.product.id, this.quantity + change);

                        if (outcome === 'superseded') {
                            return;
                        }

                        if (outcome === 'refused' && message) {
                            this.$cardAnnounce(this.$el, message);

                            return;
                        }

                        this.$cardAnnounce(this.$el, this.quantity > 0
                            ? this.label('cartQuantity', { count: this.quantity })
                            : buyText.removed);
                    },
                },
            });
        </script>
    @endPushOnce
@endif
