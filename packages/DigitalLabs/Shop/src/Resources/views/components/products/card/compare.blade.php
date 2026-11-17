{{--
    Compare on the Swipe and Photo deck cards. variant: 'checkbox' (a tick box with the word "Compare",
    Swipe card) or 'icon' (an outlined two-arrows button beside the price, Photo deck card). Both tick
    and untick, through $cardCompare (card/styled-runtime).
--}}
@props(['variant'])

{!! view_render_event('qubix.shop.components.products.card.compare_option.before') !!}

@if (core()->getConfigData('catalog.products.settings.compare_option'))
    @include('shop::components.products.card.styled-runtime')

    <v-card-compare
        {{ $attributes }}
        variant="{{ $variant }}"
    ></v-card-compare>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-card-compare-template"
        >
            <button
                type="button"
                class="pc-compare pc-compare--checkbox"
                :aria-pressed="isInCompare ? 'true' : 'false'"
                @click="toggle"
                v-if="variant === 'checkbox'"
            >
                <span
                    class="pc-compare-box"
                    aria-hidden="true"
                >
                    <x-shop::products.card.icon name="check" />
                </span>

                <span>@lang('shop::app.components.products.card.compare')</span>

                <span
                    class="sr-only"
                    v-text="product.name"
                ></span>
            </button>

            <button
                type="button"
                class="pc-compare pc-icon-button"
                :aria-pressed="isInCompare ? 'true' : 'false'"
                :aria-label="label"
                @click="toggle"
                v-else
            >
                <x-shop::products.card.icon name="compare" />
            </button>
        </script>

        <script type="module">
            const compareText = {{ Js::from([
                'compareProduct' => trans('shop::app.components.products.card.compare-product'),
            ]) }};

            app.component('v-card-compare', {
                template: '#v-card-compare-template',

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
                        isInCompare: false,

                        busy: false,
                    };
                },

                computed: {
                    label() {
                        return this.$fillPlaceholders(compareText.compareProduct, { name: this.product.name });
                    },
                },

                watch: {
                    /*
                     * Grids reuse card components when their product list changes (filters, paging).
                     */
                    'product.id'() {
                        this.sync();
                    },
                },

                created() {
                    this.onCompareChanged = () => this.sync();
                },

                mounted() {
                    this.$emitter.on('card-compare-changed', this.onCompareChanged);

                    this.$cardCompare.cardMounted();

                    this.sync();
                },

                beforeUnmount() {
                    this.$emitter.off('card-compare-changed', this.onCompareChanged);

                    this.$cardCompare.cardUnmounted();
                },

                methods: {
                    sync() {
                        this.isInCompare = this.$cardCompare.has(this.product.id);
                    },

                    async toggle() {
                        if (this.busy) {
                            return;
                        }

                        this.busy = true;

                        try {
                            if (this.isInCompare) {
                                await this.$cardCompare.remove(this.product.id);
                            } else {
                                await this.$cardCompare.add(this.product);
                            }
                        } finally {
                            this.busy = false;
                        }
                    },
                },
            });
        </script>
    @endPushOnce
@endif

{!! view_render_event('qubix.shop.components.products.card.compare_option.after') !!}
