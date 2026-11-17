{{--
    Add to cart on the Swipe and Photo deck cards. variant: 'full' (a full-width button under the price,
    Swipe card) or 'round' (a round bag button on the photo, Photo deck card). Adds through $cardCart
    (card/styled-runtime), the same cart API call as the Classic card.
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
            </div>
        </script>

        <script type="module">
            const buyText = {{ Js::from([
                'quickAdd' => trans('shop::app.components.products.card.quick-add'),
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
                        state: 'idle',
                    };
                },

                methods: {
                    label(key) {
                        return this.$fillPlaceholders(buyText[key], { name: this.product.name });
                    },

                    async add() {
                        if (this.state !== 'idle') {
                            return;
                        }

                        this.state = 'loading';

                        const added = await this.$cardCart.add(this.product);

                        this.state = added ? 'added' : 'idle';

                        if (added) {
                            setTimeout(() => this.state = 'idle', 900);
                        }
                    },
                },
            });
        </script>
    @endPushOnce
@endif
