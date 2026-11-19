{{--
    Page-wide state of the Swipe and Photo deck cards, one copy per page: the compare list
    ($cardCompare) and the cart ($cardCart). Plain objects on app.config.globalProperties that announce
    every change on the event bus, so all cards and the compare tray stay in step. The Classic card does
    not use any of this.
--}}
@include('shop::components.compare.list')

@include('shop::components.products.card.cart-writes')

{{-- Named, so the compare tray can tell that the page has new-style cards (card/compare-tray). --}}
@pushOnce('scripts', 'shop-card-styled-runtime')
    <script type="module">
        const { $axios: axios, $emitter: emitter, $fillPlaceholders: fill } = app.config.globalProperties;

        const isCustomer = @json(auth()->guard('customer')->check());

        const routes = {{ Js::from([
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

            /*
             * Which read of the list is the latest: an older answer that arrives late is dropped.
             */
            reads: 0,

            /*
             * True while this object writes the guest list itself, so it does not read back its own change.
             */
            writing: false,

            /**
             * Read the list once per page, on the first card's mount. add(), remove() and clear() wait
             * for it, so a change made while it is on its way is not overwritten by the list it reads.
             * A failed read leaves the list as it was; the cards still work.
             */
            load() {
                this.loading ??= this.fetch().catch(() => {});

                return this.loading;
            },

            /**
             * Read the list again after a change made elsewhere: the product page's or the Classic card's
             * add (compare-list-changed), another tab (storage), or Back to a page kept by the browser
             * (pageshow). Only once the page has read it at all.
             */
            refresh() {
                if (this.writing || ! this.loading) {
                    return;
                }

                this.loading = null;

                this.load();
            },

            async fetch() {
                const read = ++this.reads;

                const guestIds = isCustomer ? [] : compareList().guestIds();

                if (! isCustomer && ! guestIds.length) {
                    this.ids = [];

                    this.changed();

                    return;
                }

                const response = await axios.get(routes.compare, {
                    params: isCustomer ? {} : { product_ids: guestIds },
                });

                if (read !== this.reads) {
                    return;
                }

                response.data.data.forEach(product => this.remember(product));

                if (isCustomer) {
                    this.ids = response.data.data.map(product => product.id);
                } else {
                    // A saved product the shop no longer shows leaves the list, so the count and the limit agree.
                    guestIds
                        .filter(id => ! response.data.data.some(product => product.id == id))
                        .forEach(id => this.quietly(() => compareList().removeForGuest(id)));

                    this.ids = compareList().guestIds().filter(id => this.products[id]);
                }

                this.changed();
            },

            /**
             * Run a guest list write of this object's own, without reading the list back for it.
             */
            quietly(write) {
                this.writing = true;

                try {
                    return write();
                } finally {
                    this.writing = false;
                }
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
                await this.load();

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
                } else if (this.quietly(() => compareList().addForGuest(product.id)) === 'full') {
                    return;
                }

                this.remember(product);

                this.ids = [...this.ids, product.id];

                flash('success', fill(runtimeText.compareAdded, { count: this.ids.length, max: this.limit }));

                this.changed();
            },

            async remove(productId) {
                await this.load();

                if (isCustomer) {
                    try {
                        await axios.post(routes.compareDestroy, { _method: 'DELETE', product_id: productId });
                    } catch (error) {
                        flash('error', error.response?.data?.message);

                        return;
                    }
                } else {
                    this.quietly(() => compareList().removeForGuest(productId));
                }

                this.ids = this.ids.filter(id => id !== productId);

                flash('success', runtimeText.compareRemoved);

                this.changed();
            },

            async clear() {
                await this.load();

                if (isCustomer) {
                    try {
                        await axios.post(routes.compareDestroyAll, { _method: 'DELETE' });
                    } catch (error) {
                        flash('error', error.response?.data?.message);

                        return;
                    }
                } else {
                    this.quietly(() => compareList().clearForGuest());
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

        emitter.on('compare-list-changed', () => cardCompare.refresh());

        window.addEventListener('storage', event => {
            if (event.key === 'compare_items' || event.key === null) {
                cardCompare.refresh();
            }
        });

        window.addEventListener('pageshow', event => {
            if (event.persisted) {
                cardCompare.refresh();
            }
        });

        /* ---- cart: start ---- */
        /*
         * The cart, as the new cards see it: for each product a card can step, its one cart line
         * ({ itemId, quantity }), rebuilt from every cart that is broadcast on update-mini-cart (these
         * cards, the Classic card, the product page, the mini cart, $cartWrites' reads). Adds and quantity
         * changes go through $cartWrites (card/cart-writes), which sends them in a safe order, with the
         * cart page's own calls, so the same stock rules apply (Cart::updateItems); a line is removed at 0.
         * Every change is announced as card-cart-changed.
         *
         * The cards show every change at once: Add to cart and quick clicks on − or + only move the
         * wanted quantity (`wanted`), and the next write for the product sends the latest one, so five
         * quick clicks cost one or two requests. A refused change shows the cart's message and goes back
         * to what the cart really holds.
         */
        // Looked up at each use, not when this script runs: card/cart-writes' script can come later on
        // the page (both are pushed once, from whichever card view renders first).
        const cartWrites = () => app.config.globalProperties.$cartWrites;

        const cardCart = {
            lines: {},

            wanted: {},

            changes: {},

            loading: null,

            /**
             * Read the cart once per page, on the first card's mount. It waits for nothing, and nothing
             * waits for it.
             */
            load() {
                this.loading ??= cartWrites().read();

                return this.loading;
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
             * Add one to the cart (resolves like $cartWrites.add()). A product with nothing to choose is
             * counted at once, so its card turns into the − 1 + control straight away; once the cart has
             * answered, the card shows the cart's own quantity again, unless − or + was clicked meanwhile
             * (that write follows this one and settles it).
             *
             * When the product is already in the cart, or on its way (Add, −, Add), this asks for one more
             * than the card shows, sent like − and +: in order behind them. Another "add one" would land on
             * top of a − still waiting its turn, and the cart would end up with one too many.
             */
            async add(product) {
                const instant = cartWrites().isInstant(product);

                if (instant && (this.lines[product.id] || this.wanted[product.id] !== undefined)) {
                    cartWrites().flashAdded();

                    const { outcome, message } = await this.setQuantity(product.id, this.quantity(product.id) + 1);

                    return outcome === 'refused' ? { added: false, message } : { added: true };
                }

                const change = this.bump(product.id);

                if (instant) {
                    this.wanted[product.id] = this.quantity(product.id) + 1;

                    this.changed();
                }

                const result = await cartWrites().add(product);

                if (instant && (! result.added || this.changes[product.id] === change)) {
                    delete this.wanted[product.id];

                    this.changed();
                }

                return result;
            },

            /**
             * Count a change to the product's wanted quantity; an add that answers later only clears what
             * it set if nothing has changed since.
             */
            bump(productId) {
                this.changes[productId] = (this.changes[productId] ?? 0) + 1;

                return this.changes[productId];
            },

            /**
             * Ask for `quantity` of a product that has a line, or is on its way to the cart (0 removes the
             * line). Resolves with how it went: { outcome: 'done' } once the cart holds what this call
             * asked for and nothing newer is waiting; { outcome: 'superseded' } when a later call took it
             * over (that call reports); { outcome: 'refused', message } when the cart said no (message: the
             * cart's reason, if it gave one), by which time the card shows what the cart really holds again.
             */
            setQuantity(productId, quantity) {
                if (! this.lines[productId] && this.wanted[productId] === undefined) {
                    return Promise.resolve({ outcome: 'refused' });
                }

                this.wanted[productId] = Math.max(0, quantity);

                this.bump(productId);

                this.changed();

                return cartWrites().run(productId, () => this.write(productId));
            },

            remove(productId) {
                return this.setQuantity(productId, 0);
            },

            /**
             * Send the product's wanted quantity, if it still differs from the cart's (see setQuantity()).
             */
            async write(productId) {
                const quantity = this.wanted[productId];

                const line = this.lines[productId];

                if (quantity === undefined) {
                    return { outcome: 'superseded' };
                }

                if (quantity === (line?.quantity ?? 0)) {
                    delete this.wanted[productId];

                    this.changed();

                    return { outcome: 'done' };
                }

                // No line (yet, or any more: say a − removed it while Add was tapped again): make one.
                const refusal = ! line
                    ? await this.create(productId, quantity)
                    : (quantity > 0 ? await this.update(line.itemId, quantity) : await this.destroy(line.itemId));

                if (refusal) {
                    // Back to what the cart really holds, then make sure of it.
                    delete this.wanted[productId];

                    this.changed();

                    await cartWrites().read();

                    return { outcome: 'refused', message: refusal.message };
                }

                if (this.wanted[productId] !== quantity) {
                    return { outcome: 'superseded' };
                }

                delete this.wanted[productId];

                this.changed();

                return { outcome: 'done' };
            },

            /**
             * Show a refusal and hand it back as { message } (the message can be empty).
             */
            refused(type, message) {
                flash(type, message);

                return { message };
            },

            /**
             * The cart page's quantity call (and the mini cart's): a message next to the cart means it
             * worked; otherwise data.message says why not (for example the inventory warning).
             * Resolves null when it worked, else the refusal.
             */
            async update(itemId, quantity) {
                try {
                    const response = await axios.put(routes.cartUpdate, { qty: { [itemId]: quantity } });

                    if (response.data.message) {
                        emitter.emit('update-mini-cart', response.data.data);

                        return null;
                    }

                    return this.refused('warning', response.data.data.message);
                } catch (error) {
                    return this.refused('error', error.response?.data?.message);
                }
            },

            /**
             * Put `quantity` of a product that has no line into the cart. Resolves null when it worked,
             * else the refusal.
             */
            async create(productId, quantity) {
                try {
                    const response = await axios.post(routes.cartStore, { product_id: productId, quantity });

                    if (response.data.message) {
                        emitter.emit('update-mini-cart', response.data.data);

                        return null;
                    }

                    return this.refused('warning', response.data.data?.message);
                } catch (error) {
                    return this.refused('error', error.response?.data?.message);
                }
            },

            /**
             * The mini cart's remove call, without its confirm dialog at 0 (the design's behaviour).
             * Resolves null when it worked, else the refusal.
             */
            async destroy(itemId) {
                try {
                    const response = await axios.post(routes.cartDestroy, { _method: 'DELETE', cart_item_id: itemId });

                    emitter.emit('update-mini-cart', response.data.data);

                    flash('success', response.data.message);

                    return null;
                } catch (error) {
                    return this.refused('error', error.response?.data?.message);
                }
            },

            changed() {
                emitter.emit('card-cart-changed');
            },
        };

        emitter.on('update-mini-cart', cart => cardCart.apply(cart));
        /* ---- cart: end ---- */

        /* ---- fly to cart: start ---- */
        // Looked up when used: card/cart-writes, which sets it, may be pushed after this script.
        const cartIcon = () => app.config.globalProperties.$cartIcon;

        /**
         * After Add to cart: a small photo of the product arcs from the button to the header cart (x runs
         * linearly, y eases in; design concepts, fly()), and the cart icon bumps. With reduced motion, or
         * when the cart icon is outside the screen, nothing flies: the icon's count fades in instead.
         * The photo is decoration only: hidden from assistive technology, never in the way of a click,
         * and removed again whether the flight finishes or is cancelled. The add itself is announced by
         * the card (pc-announce), not here.
         */
        const flyToCart = (from, imageUrl) => {
            const target = cartIcon().target();

            if (! target || ! from?.isConnected) {
                return;
            }

            const end = target.getBoundingClientRect();

            const start = from.getBoundingClientRect();

            const x0 = start.left + start.width / 2;
            const y0 = start.top + start.height / 2;
            const x1 = end.left + end.width / 2;
            const y1 = end.top + end.height / 2;

            // Off screen on any side (scrolled away, or beyond the edge): there is nothing to fly to.
            const onScreen = x1 >= 0 && x1 <= document.documentElement.clientWidth
                && y1 >= 0 && y1 <= document.documentElement.clientHeight;

            if (cartIcon().prefersReducedMotion() || ! onScreen || ! start.width) {
                cartIcon().flash(target);

                return;
            }

            const flight = document.createElement('div');

            flight.className = 'pc-fly';
            flight.setAttribute('aria-hidden', 'true');
            flight.innerHTML = '<div class="pc-fly-y"><div class="pc-fly-dot"><img alt=""></div></div>';

            if (imageUrl) {
                flight.querySelector('img').src = imageUrl;
            }

            document.body.append(flight);

            // Nothing that goes wrong from here on may leave the photo behind.
            try {
                const vertical = flight.firstElementChild;

                const duration = 720;

                const animations = [
                    flight.animate(
                        [{ transform: `translateX(${x0}px)` }, { transform: `translateX(${x1}px)` }],
                        { duration, easing: 'cubic-bezier(0.3, 0, 0.6, 1)', fill: 'forwards' }
                    ),
                    vertical.animate(
                        [{ transform: `translateY(${y0}px)` }, { transform: `translateY(${y0 - 40}px)`, offset: 0.3 }, { transform: `translateY(${y1}px)` }],
                        { duration, easing: 'cubic-bezier(0.5, 0, 0.75, 0)', fill: 'forwards' }
                    ),
                    vertical.firstElementChild.animate(
                        [{ transform: 'scale(0.6)', opacity: 0 }, { transform: 'scale(1.05)', opacity: 1, offset: 0.2 }, { transform: 'scale(0.35)', opacity: 0.9 }],
                        { duration, easing: 'ease-in', fill: 'forwards' }
                    ),
                ];

                // `finished` rejects when an animation is cancelled: no bump then, but the photo still goes.
                Promise.all(animations.map(animation => animation.finished))
                    .then(() => cartIcon().bump(target))
                    .catch(() => {})
                    .finally(() => flight.remove());
            } catch {
                flight.remove();
            }
        };

        app.config.globalProperties.$cardFlyToCart = flyToCart;
        /* ---- fly to cart: end ---- */

        app.config.globalProperties.$cardCompare = cardCompare;

        app.config.globalProperties.$cardCart = cardCart;
    </script>
@endPushOnce
