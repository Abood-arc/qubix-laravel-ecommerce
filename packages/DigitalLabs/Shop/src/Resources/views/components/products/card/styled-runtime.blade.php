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
         * The cart, as the new cards see it: for each product a card can step, its one cart line
         * ({ itemId, quantity }). Read once per page from the cart API, then rebuilt from every cart that
         * is broadcast on update-mini-cart (these cards, the Classic card, the product page, the mini
         * cart). Quantity changes use the cart page's own calls, so the same stock rules apply
         * (Cart::updateItems); a line is removed at 0. Every change is announced as card-cart-changed.
         *
         * Writes go out one at a time, in order, so carts come back in the order they were asked for.
         * Quick clicks on − or + only move the wanted quantity (`wanted`), which the cards show at once;
         * the next write sends the latest one, so five quick clicks cost one or two requests. A refused
         * change shows the cart's message and goes back to what the cart really holds.
         */
        const cardCart = {
            lines: {},

            wanted: {},

            queue: Promise.resolve(),

            loading: null,

            /**
             * Run `task` after every write asked for before it.
             */
            enqueue(task) {
                const run = this.queue.then(task);

                this.queue = run.catch(() => {});

                return run;
            },

            load() {
                this.loading ??= this.enqueue(() => this.fetch());

                return this.loading;
            },

            fetch() {
                return axios.get(routes.cart)
                    .then(response => this.apply(response.data.data))
                    .catch(() => {});
            },

            /**
             * A card steps a product only when the cart has exactly one plain line for it: no options,
             * quantity changeable. Configurable, bundle and grouped lines are left to the cart page.
             */
            apply(cart) {
                const items = cart?.items ?? [];

                const linesPerProduct = {};

                items.forEach(item => {
                    linesPerProduct[item.product_id] = (linesPerProduct[item.product_id] ?? 0) + 1;
                });

                this.lines = {};

                items.forEach(item => {
                    if (linesPerProduct[item.product_id] === 1 && item.can_change_qty && ! (item.options ?? []).length) {
                        this.lines[item.product_id] = { itemId: item.id, quantity: Number(item.quantity) };
                    }
                });

                this.changed();
            },

            /**
             * What the card shows: the quantity on its way to the cart, else the cart's.
             */
            quantity(productId) {
                return this.wanted[productId] ?? this.lines[productId]?.quantity ?? 0;
            },

            /**
             * Add to cart: the Classic card's call with the same messages.
             */
            add(product) {
                // Same cart call as the Classic card's addToCart (products/card.blade.php), which stays untouched.
                return this.enqueue(async () => {
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
                });
            },

            /**
             * Ask for `quantity` of a product that has a line (0 removes the line). Resolves true once
             * the cart holds what this call asked for and nothing newer is waiting; false when the cart
             * refused it, or when a later call took it over.
             */
            setQuantity(productId, quantity) {
                if (! this.lines[productId]) {
                    return Promise.resolve(false);
                }

                this.wanted[productId] = Math.max(0, quantity);

                this.changed();

                return this.enqueue(() => this.write(productId));
            },

            remove(productId) {
                return this.setQuantity(productId, 0);
            },

            /**
             * Send the product's wanted quantity, if it still differs from the cart's.
             */
            async write(productId) {
                const quantity = this.wanted[productId];

                const line = this.lines[productId];

                if (quantity === undefined) {
                    return false;
                }

                if (! line || quantity === line.quantity) {
                    delete this.wanted[productId];

                    this.changed();

                    return Boolean(line);
                }

                const done = quantity > 0
                    ? await this.update(line.itemId, quantity)
                    : await this.destroy(line.itemId);

                if (! done) {
                    // Back to what the cart really holds, then make sure of it.
                    delete this.wanted[productId];

                    this.changed();

                    await this.refresh();

                    return false;
                }

                if (this.wanted[productId] !== quantity) {
                    return false;
                }

                delete this.wanted[productId];

                this.changed();

                return true;
            },

            /**
             * The cart page's quantity call (and the mini cart's): a message next to the cart means it
             * worked; otherwise data.message says why not (for example the inventory warning).
             */
            async update(itemId, quantity) {
                try {
                    const response = await axios.put(routes.cartUpdate, { qty: { [itemId]: quantity } });

                    if (response.data.message) {
                        emitter.emit('update-mini-cart', response.data.data);

                        return true;
                    }

                    flash('warning', response.data.data.message);
                } catch (error) {
                    flash('error', error.response?.data?.message);
                }

                return false;
            },

            /**
             * The mini cart's remove call, without its confirm dialog at 0 (the design's behaviour).
             */
            async destroy(itemId) {
                try {
                    const response = await axios.post(routes.cartDestroy, { _method: 'DELETE', cart_item_id: itemId });

                    emitter.emit('update-mini-cart', response.data.data);

                    flash('success', response.data.message);

                    return true;
                } catch (error) {
                    flash('error', error.response?.data?.message);

                    return false;
                }
            },

            /**
             * After a refused change: read the cart again and tell everyone (the header count too).
             */
            refresh() {
                return axios.get(routes.cart)
                    .then(response => emitter.emit('update-mini-cart', response.data.data))
                    .catch(() => {});
            },

            changed() {
                emitter.emit('card-cart-changed');
            },
        };

        emitter.on('update-mini-cart', cart => cardCart.apply(cart));
        /* ---- cart: end ---- */

        app.config.globalProperties.$cardCompare = cardCompare;

        app.config.globalProperties.$cardCart = cardCart;
    </script>
@endPushOnce
