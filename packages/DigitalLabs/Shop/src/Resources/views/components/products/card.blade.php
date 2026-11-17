<v-product-card
    {{ $attributes }}
    :product="product"
>
</v-product-card>

@pushOnce('scripts')
    @php
        // Admin > Configuration > General > Design > Product card, read once per page. The grid part
        // of the template is picked here, so a page carries only its own style's markup and scripts,
        // and the Classic template is exactly today's card. The cart and compare pages always get
        // Classic (ProductCardStyleEnum::CLASSIC_ONLY_ROUTES).
        $cardStyle = \DigitalLabs\Shop\Enums\ProductCardStyleEnum::forPage();

        // Unit of the products' Width, Height and Length attributes (Product card > Size unit).
        $sizeUnit = core()->getConfigData('general.design.product_card.size_unit') === 'cm' ? 'cm' : 'in';

        $sizeUnitLong = trans('shop::app.components.products.card.unit-'.$sizeUnit.'-long');
    @endphp

    <script
        type="text/x-template"
        id="v-product-card-template"
    >
        @include('shop::components.products.card.grid-'.$cardStyle->value)

        <!-- List Card -->
        <div
            class="relative flex max-w-max grid-cols-2 gap-4 overflow-hidden rounded-xl transition-all duration-300 hover:shadow-[0_12px_32px_rgba(0,0,0,0.10)] max-sm:flex-wrap"
            v-else
        >
            <div class="group relative max-h-[258px] max-w-[250px] overflow-hidden rounded-xl">

                {!! view_render_event('qubix.shop.components.products.card.image.before') !!}

                <a :href="`{{ route('shop.product_or_category.index', '') }}/${product.url_key}`">
                    <x-shop::media.images.lazy
                        class="after:content-[' '] relative min-w-[250px] bg-zinc-100 transition-all duration-500 ease-[cubic-bezier(0.25,0.46,0.45,0.94)] after:block after:pb-[calc(100%+9px)] group-hover:scale-110"
                        ::src="product.base_image.medium_image_url"
                        ::key="product.id"
                        ::index="product.id"
                        width="291"
                        height="300"
                        ::alt="product.name"
                    />
                </a>

                <!-- Depth overlay for list card -->
                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/25 via-transparent to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                    aria-hidden="true"
                ></div>

                {!! view_render_event('qubix.shop.components.products.card.image.after') !!}

                <div class="action-items bg-black">
                    <p
                        class="absolute top-5 inline-block rounded-[44px] bg-red-500 px-2.5 text-sm text-white ltr:left-5 max-sm:ltr:left-2 rtl:right-5"
                        v-if="product.on_sale"
                    >
                        @lang('shop::app.components.products.card.sale')
                    </p>

                    <p
                        class="absolute top-5 inline-block rounded-[44px] bg-navyBlue px-2.5 text-sm text-white ltr:left-5 max-sm:ltr:left-2 rtl:right-5"
                        v-else-if="product.is_new"
                    >
                        @lang('shop::app.components.products.card.new')
                    </p>

                    <div class="opacity-0 transition-all duration-300 group-hover:bottom-0 group-hover:opacity-100 max-sm:opacity-100">

                        {!! view_render_event('qubix.shop.components.products.card.wishlist_option.before') !!}

                        @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                            <span
                                class="absolute top-5 flex h-[30px] w-[30px] cursor-pointer items-center justify-center rounded-md bg-white text-2xl ltr:right-5 rtl:left-5"
                                role="button"
                                aria-label="@lang('shop::app.components.products.card.add-to-wishlist')"
                                tabindex="0"
                                :class="product.is_wishlist ? 'icon-heart-fill text-red-600' : 'icon-heart'"
                                @click="addToWishlist()"
                            >
                            </span>
                        @endif

                        {!! view_render_event('qubix.shop.components.products.card.wishlist_option.after') !!}

                        {!! view_render_event('qubix.shop.components.products.card.compare_option.before') !!}

                        @if (core()->getConfigData('catalog.products.settings.compare_option'))
                            <span
                                class="icon-compare absolute top-16 flex h-[30px] w-[30px] cursor-pointer items-center justify-center rounded-md bg-white text-2xl ltr:right-5 rtl:left-5"
                                role="button"
                                aria-label="@lang('shop::app.components.products.card.add-to-compare')"
                                tabindex="0"
                                @click="addToCompare(product.id)"
                            >
                            </span>
                        @endif

                        {!! view_render_event('qubix.shop.components.products.card.compare_option.after') !!}
                    </div>
                </div>
            </div>

            <div class="grid content-start gap-4">

                {!! view_render_event('qubix.shop.components.products.card.name.before') !!}

                <p class="text-base">
                    @{{ product.name }}
                </p>

                {!! view_render_event('qubix.shop.components.products.card.name.after') !!}

                {!! view_render_event('qubix.shop.components.products.card.price.before') !!}

                <div
                    class="flex gap-2.5 text-lg font-semibold"
                    v-html="product.price_html"
                >
                </div>

                {!! view_render_event('qubix.shop.components.products.card.price.after') !!}

                <!-- Needs to implement that in future -->
                <div class="flex hidden gap-4">
                    <span class="block h-[30px] w-[30px] rounded-full bg-[#B5DCB4]">
                    </span>

                    <span class="block h-[30px] w-[30px] rounded-full bg-zinc-500">
                    </span>
                </div>

                {!! view_render_event('qubix.shop.components.products.card.average_ratings.before') !!}

                <p class="text-sm text-zinc-500">
                    <template  v-if="! product.ratings.total">
                        <p class="text-sm text-zinc-500">
                            @lang('shop::app.components.products.card.review-description')
                        </p>
                    </template>

                    <template v-else>
                        @if (core()->getConfigData('catalog.products.review.summary') == 'star_counts')
                            <x-shop::products.ratings
                                ::average="product.ratings.average"
                                ::total="product.ratings.total"
                                ::rating="false"
                            />
                        @else
                            <x-shop::products.ratings
                                ::average="product.ratings.average"
                                ::total="product.reviews.total"
                                ::rating="false"
                            />
                        @endif
                    </template>
                </p>

                {!! view_render_event('qubix.shop.components.products.card.average_ratings.after') !!}

                @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))

                    {!! view_render_event('qubix.shop.components.products.card.add_to_cart.before') !!}

                    <x-shop::button
                        class="primary-button whitespace-nowrap px-8 py-2.5"
                        :title="trans('shop::app.components.products.card.add-to-cart')"
                        ::loading="isAddingToCart"
                        ::disabled="! product.is_saleable || isAddingToCart"
                        @click="addToCart()"
                    />

                    {!! view_render_event('qubix.shop.components.products.card.add_to_cart.after') !!}

                @endif
            </div>
        </div>
    </script>

    @include('shop::components.compare.list')

    <script type="module">
        /**
         * Copy used by the Swipe and Photo deck card templates (":name" style placeholders, filled by text()).
         */
        const cardText = {{ Js::from([
            'discount' => trans('shop::app.components.products.card.discount'),
            'onlyLeft' => trans('shop::app.components.products.card.only-left'),
            'saveToWishlist' => trans('shop::app.components.products.card.save-to-wishlist'),
            'sizeSpoken' => trans('shop::app.components.products.card.size-spoken', ['unit' => $sizeUnitLong]),
            'sizeSpokenDepth' => trans('shop::app.components.products.card.size-spoken-depth', ['unit' => $sizeUnitLong]),
        ]) }};

        app.component('v-product-card', {
            template: '#v-product-card-template',

            props: ['mode', 'product'],

            data() {
                return {
                    isCustomer: '{{ auth()->guard('customer')->check() }}',

                    isAddingToCart: false,

                    /*
                     * The Swipe and Photo deck hearts burst when a product is saved; Classic never reads this.
                     */
                    wishlistPopping: false,
                }
            },

            /*
             * Values for the Swipe and Photo deck templates. Computed values are lazy, so the Classic
             * template, which reads none of them, never evaluates them.
             */
            computed: {
                productUrl() {
                    return `{{ route('shop.product_or_category.index', '') }}/${this.product.url_key}`;
                },

                /**
                 * Whole-number discount from the regular and final price, or 0 when there is none.
                 */
                discountPercent() {
                    const regular = Number(this.product.prices?.regular?.price);

                    const final = Number(this.product.prices?.final?.price);

                    if (! (regular > final && final > 0)) {
                        return 0;
                    }

                    return Math.round((regular - final) / regular * 100);
                },

                /**
                 * "16 × 14 × 5" from the product's dimensions (depth only when set).
                 */
                sizeNumbers() {
                    const { width, height, depth } = this.product.dimensions ?? {};

                    return [width, height, depth].filter(Boolean).join(' × ');
                },

                /**
                 * The size line for screen readers: "Size: 16 by 14 inches, 5 inches deep".
                 */
                sizeSpoken() {
                    const size = this.product.dimensions;

                    if (! size) {
                        return '';
                    }

                    return this.text(size.depth ? 'sizeSpokenDepth' : 'sizeSpoken', {
                        width: size.width,
                        height: size.height,
                        depth: size.depth,
                    });
                },
            },

            methods: {
                /**
                 * A line of card copy with its ":placeholders" filled in.
                 */
                text(key, replacements = {}) {
                    return this.$fillPlaceholders(cardText[key], replacements);
                },

                addToWishlist() {
                    if (this.isCustomer) {
                        this.$axios.post(`{{ route('shop.api.customers.account.wishlist.store') }}`, {
                                product_id: this.product.id
                            })
                            .then(response => {
                                this.product.is_wishlist = ! this.product.is_wishlist;

                                this.popWishlistHeart();

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.data.message });
                            })
                            .catch(error => {});
                        } else {
                            window.location.href = "{{ route('shop.customer.session.index')}}";
                        }
                },

                /**
                 * The new cards' heart fills with a small burst when a product is saved. Only sets
                 * wishlistPopping, which the Classic template does not read.
                 */
                popWishlistHeart() {
                    if (! this.product.is_wishlist) {
                        return;
                    }

                    this.wishlistPopping = true;

                    setTimeout(() => this.wishlistPopping = false, 650);
                },

                addToCompare(productId) {
                    /**
                     * This will handle for customers.
                     */
                    if (this.isCustomer) {
                        this.$axios.post('{{ route("shop.api.compare.store") }}', {
                                'product_id': productId
                            })
                            .then(response => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.data.message });
                            })
                            .catch(error => {
                                if (this.$compareList.handleLimitError(error)) {
                                    return;
                                }

                                if ([400, 422].includes(error.response.status)) {
                                    this.$emitter.emit('add-flash', { type: 'warning', message: error.response.data.data.message });

                                    return;
                                }

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message});
                            });

                        return;
                    }

                    /**
                     * This will handle for guests. $compareList keeps the list within the compare limit
                     * and shows the limit message itself.
                     */
                    const result = this.$compareList.addForGuest(productId);

                    if (result === 'added') {
                        this.$emitter.emit('add-flash', { type: 'success', message: "@lang('shop::app.components.products.card.add-to-compare-success')" });
                    } else if (result === 'already') {
                        this.$emitter.emit('add-flash', { type: 'warning', message: "@lang('shop::app.components.products.card.already-in-compare')" });
                    }
                },

                addToCart() {
                    this.isAddingToCart = true;

                    this.$axios.post('{{ route("shop.api.checkout.cart.store") }}', {
                            'quantity': 1,
                            'product_id': this.product.id,
                        })
                        .then(response => {
                            if (response.data.message) {
                                this.$emitter.emit('update-mini-cart', response.data.data );

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            } else {
                                this.$emitter.emit('add-flash', { type: 'warning', message: response.data.data.message });
                            }

                            this.isAddingToCart = false;
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });

                            if (error.response.data.redirect_uri) {
                                window.location.href = error.response.data.redirect_uri;
                            }

                            this.isAddingToCart = false;
                        });
                },
            },
        });
    </script>
@endpushOnce
