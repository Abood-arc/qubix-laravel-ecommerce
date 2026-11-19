{{--
    $cartWrites: how every product card writes to the cart (Classic, Swipe and Photo deck). Pushed once per page.

    - Instant add: a product that goes straight into the cart (simple or virtual: nothing to choose) shows
      as added the moment it is tapped: the success message, and the header cart count, which counts it
      while the request is on its way (cart-adds-pending, read by the mini cart). The cart's answer then
      confirms it, or, when the cart refuses, the count goes back and the cart's reason shows. Other
      products (options to choose on their page) wait for the cart's answer, as before.
    - Order: writes for one product go out one after the other, so they reach the cart in the order they
      were asked for; writes for different products go out side by side. Until the shopper is known to
      have a cart, writes go one at a time: the server makes the cart on the first add
      (Cart::addProduct), and two first adds at once would make two carts, one of them lost.
    - Writes that were out at the same time can each answer with a cart that misses the other's change,
      so once the last of them is back the cart is read again.
    - Every cart it gets is broadcast on update-mini-cart. A read is dropped when a write has started
      since it was asked for: that write's answer is the newer cart.
--}}
@pushOnce('scripts', 'shop-card-cart-writes')
    <script type="module">
        const { $axios: axios, $emitter: emitter } = app.config.globalProperties;

        const routes = {{ Js::from([
            'cart' => route('shop.api.checkout.cart.index'),
            'cartStore' => route('shop.api.checkout.cart.store'),
        ]) }};

        const cartText = {{ Js::from([
            'added' => trans('shop::app.checkout.cart.item-add-to-cart'),
            'addFailed' => trans('shop::app.checkout.cart.add-failed'),
        ]) }};

        const flash = (type, message) => emitter.emit('add-flash', { type, message });

        const cartWrites = {
            /*
             * Whether the shopper has a cart, from the latest cart seen. Unknown counts as no.
             */
            hasCart: false,

            /*
             * The last write asked for while there was no cart; the next such write waits for it.
             */
            firstWrites: Promise.resolve(),

            /*
             * For each product, the last write asked for; the product's next write waits for it.
             */
            productWrites: new Map(),

            inFlight: 0,

            overlapped: false,

            /*
             * How many writes have started. A read asked for before the latest one is out of date.
             */
            started: 0,

            /*
             * Units of each product (by id) that an instant add is bringing to the cart.
             */
            pending: {},

            /**
             * Whether a product goes into the cart as it is, so a card can show it as added at once.
             */
            isInstant(product) {
                return ['simple', 'virtual'].includes(product.type);
            },

            /**
             * Run `task` (a cart write for the product) once the writes it must follow are done (see above).
             */
            run(productId, task) {
                const before = [this.productWrites.get(productId)];

                if (! this.hasCart) {
                    before.push(this.firstWrites);
                }

                const run = Promise.all(before).then(() => this.track(task));

                const settled = run.then(() => {}, () => {});

                if (! this.hasCart) {
                    this.firstWrites = settled;
                }

                this.productWrites.set(productId, settled);

                return run;
            },

            async track(task) {
                this.started++;

                this.overlapped ||= this.inFlight > 0;

                this.inFlight++;

                try {
                    return await task();
                } finally {
                    if (--this.inFlight === 0 && this.overlapped) {
                        this.overlapped = false;

                        this.read();
                    }
                }
            },

            /**
             * Read the cart and broadcast it, unless a write has started meanwhile. A failed read changes nothing.
             */
            read() {
                const started = this.started;

                return axios.get(routes.cart)
                    .then(response => {
                        if (started === this.started) {
                            emitter.emit('update-mini-cart', response.data.data);
                        }
                    })
                    .catch(() => {});
            },

            /**
             * Add one of the product. Resolves with { added }, plus { message } when the cart refused it
             * (the reason, which has been shown).
             */
            add(product) {
                const instant = this.isInstant(product);

                if (instant) {
                    flash('success', cartText.added);

                    this.countPending(product.id, 1);
                }

                return this.run(product.id, () => this.post(product, instant));
            },

            async post(product, instant) {
                let response;

                try {
                    response = await axios.post(routes.cartStore, { quantity: 1, product_id: product.id });
                } catch (error) {
                    return this.refused(product, instant, 'error', error.response?.data?.message, error.response?.data?.redirect_uri);
                }

                if (! response.data.message) {
                    return this.refused(product, instant, 'warning', response.data.data?.message);
                }

                // The count and the cart change together, so the header never counts the item twice.
                if (instant) {
                    this.countPending(product.id, -1);
                }

                emitter.emit('update-mini-cart', response.data.data);

                if (! instant) {
                    flash('success', response.data.message);
                }

                return { added: true };
            },

            refused(product, instant, type, message, redirectUri) {
                if (instant) {
                    this.countPending(product.id, -1);
                }

                message ||= cartText.addFailed;

                flash(type, message);

                if (redirectUri) {
                    window.location.href = redirectUri;
                }

                return { added: false, message };
            },

            countPending(productId, change) {
                this.pending[productId] = (this.pending[productId] ?? 0) + change;

                if (! this.pending[productId]) {
                    delete this.pending[productId];
                }

                emitter.emit('cart-adds-pending', { ...this.pending });
            },
        };

        emitter.on('update-mini-cart', cart => (cartWrites.hasCart = !! cart?.id));

        app.config.globalProperties.$cartWrites = cartWrites;
    </script>
@endPushOnce
