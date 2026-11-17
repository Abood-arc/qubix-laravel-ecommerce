{{--
    Page-wide state of the Swipe and Photo deck cards, one copy per page: the compare list
    ($cardCompare) and the cart ($cardCart). Plain objects on app.config.globalProperties that announce
    every change on the event bus, so all cards and the compare tray stay in step. The Classic card does
    not use any of this.
--}}
@include('shop::components.compare.list')

@pushOnce('scripts')
    <script type="module">
        const { $axios: axios, $emitter: emitter, $fillPlaceholders: fill } = app.config.globalProperties;

        const isCustomer = @json(auth()->guard('customer')->check());

        const routes = {{ Js::from([
            'cart' => route('shop.api.checkout.cart.index'),
            'cartStore' => route('shop.api.checkout.cart.store'),
            'cartUpdate' => route('shop.api.checkout.cart.update'),
            'cartDestroy' => route('shop.api.checkout.cart.destroy'),
            'compare' => route('shop.api.compare.index'),
            'compareStore' => route('shop.api.compare.store'),
            'compareDestroy' => route('shop.api.compare.destroy'),
            'compareDestroyAll' => route('shop.api.compare.destroy_all'),
        ]) }};

        const runtimeText = {{ Js::from([
            'compareAdded' => trans('shop::app.components.products.card.compare-added'),
            'compareRemoved' => trans('shop::app.components.products.card.compare-removed'),
        ]) }};

        const flash = (type, message) => emitter.emit('add-flash', { type, message });

        /*
         * The shop's one compare helper (components/compare/list): the guest list and the compare limit.
         */
        const compareList = () => app.config.globalProperties.$compareList;

        /*
         * The compare list. Guests keep it in localStorage `compare_items` (the array of product ids the
         * Classic card and the compare page use); signed-in customers keep it in their account.
         */
        const cardCompare = {
            /*
             * The most products compare holds (config shop.compare.limit). The compare endpoint and
             * $compareList enforce it; the tray only shows it.
             */
            get limit() {
                return compareList().limit;
            },

            ids: [],

            products: {},

            cards: 0,

            loading: null,

            /**
             * Read the list once per page, on the first card's mount.
             */
            load() {
                this.loading ??= this.fetch();

                return this.loading;
            },

            async fetch() {
                const guestIds = isCustomer ? [] : compareList().guestIds();

                if (isCustomer || guestIds.length) {
                    const response = await axios.get(routes.compare, {
                        params: isCustomer ? {} : { product_ids: guestIds },
                    });

                    response.data.data.forEach(product => this.remember(product));

                    this.ids = isCustomer
                        ? response.data.data.map(product => product.id)
                        : guestIds.filter(id => this.products[id]);
                }

                this.changed();
            },

            remember(product) {
                this.products[product.id] = {
                    id: product.id,
                    name: product.name,
                    image: product.base_image?.small_image_url,
                };
            },

            has(productId) {
                return this.ids.includes(productId);
            },

            // Same compare calls as the Classic card's addToCompare (products/card.blade.php), which stays untouched.
            async add(product) {
                if (this.has(product.id)) {
                    return;
                }

                if (isCustomer) {
                    try {
                        await axios.post(routes.compareStore, { product_id: product.id });
                    } catch (error) {
                        // The compare endpoint refused a fifth product: $compareList has said so.
                        if (compareList().handleLimitError(error)) {
                            return;
                        }

                        // Any other 422: already in the customer's list, which is what we want.
                        if (error.response?.status !== 422) {
                            flash('error', error.response?.data?.message);

                            return;
                        }
                    }
                } else if (compareList().addForGuest(product.id) === 'full') {
                    return;
                }

                this.remember(product);

                this.ids = [...this.ids, product.id];

                flash('success', fill(runtimeText.compareAdded, { count: this.ids.length, max: this.limit }));

                this.changed();
            },

            async remove(productId) {
                if (isCustomer) {
                    try {
                        await axios.post(routes.compareDestroy, { _method: 'DELETE', product_id: productId });
                    } catch (error) {
                        flash('error', error.response?.data?.message);

                        return;
                    }
                } else {
                    compareList().removeForGuest(productId);
                }

                this.ids = this.ids.filter(id => id !== productId);

                flash('success', runtimeText.compareRemoved);

                this.changed();
            },

            async clear() {
                if (isCustomer) {
                    try {
                        await axios.post(routes.compareDestroyAll, { _method: 'DELETE' });
                    } catch (error) {
                        flash('error', error.response?.data?.message);

                        return;
                    }
                } else {
                    compareList().clearForGuest();
                }

                this.ids = [];

                flash('success', runtimeText.compareRemoved);

                this.changed();
            },

            /**
             * Cards register themselves: the tray only shows on a page that has new-style cards.
             */
            cardMounted() {
                this.cards++;

                this.changed();

                this.load();
            },

            cardUnmounted() {
                this.cards--;

                this.changed();
            },

            changed() {
                emitter.emit('card-compare-changed', { ids: [...this.ids], cards: this.cards });
            },
        };

        /* ---- cart: start ---- */
        /*
         * The cart, as the new cards see it. Add to cart is the Classic card's call with the same messages;
         * the cart that comes back is broadcast on update-mini-cart, as everywhere else in the shop.
         */
        const cardCart = {
            // Same cart call as the Classic card's addToCart (products/card.blade.php), which stays untouched.
            async add(product) {
                try {
                    const response = await axios.post(routes.cartStore, { quantity: 1, product_id: product.id });

                    if (response.data.message) {
                        emitter.emit('update-mini-cart', response.data.data);

                        flash('success', response.data.message);

                        return true;
                    }

                    flash('warning', response.data.data.message);

                    return false;
                } catch (error) {
                    flash('error', error.response?.data?.message);

                    if (error.response?.data?.redirect_uri) {
                        window.location.href = error.response.data.redirect_uri;
                    }

                    return false;
                }
            },
        };
        /* ---- cart: end ---- */

        app.config.globalProperties.$cardCompare = cardCompare;

        app.config.globalProperties.$cardCart = cardCart;
    </script>
@endPushOnce
