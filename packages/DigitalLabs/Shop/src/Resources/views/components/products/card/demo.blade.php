{{--
    Demo mode of the product cards, for the admin's live previews only (layouts/preview, after every
    pushed script and before the app starts). It plays what a shopper does with the first card, on a
    loop: the photos turn (Swipe: the bars move; Photo deck: the top photo goes to the back), Add to cart
    becomes − 1 + and back, and Compare ticks and unticks. The Classic card has no motion of its own,
    so it stays still.

    Nothing leaves the page: the cards' cart and compare ($cardCart, $cardCompare) and the shop's compare
    helper ($compareList) are replaced by local stand-ins, and any other request for the shopper's cart,
    compare list or wishlist, or any request that would change something, is answered on the page
    without reaching the server. Links do not open. While motion is reduced (also when that is switched
    on later) the cards stay still; the loop also waits while the preview is off screen or the tab is
    hidden. Waiting runs no timer: it resumes when one of those changes back.

    The state is on <html data-demo>: "playing", "paused" or "still".
--}}
<script type="module">
    const globals = app.config.globalProperties;

    const { $axios: axios, $emitter: emitter } = globals;

    const root = document.documentElement;

    /* ---- Nothing leaves the page ---- */

    // The shopper's own things (cart, compare list, wishlist), and every request that writes.
    const PRIVATE = /\/(checkout\/cart|compare-items|wishlist)\b/;

    axios.interceptors.request.use((config) => {
        const reads = (config.method ?? 'get').toLowerCase() === 'get';

        if (reads && ! PRIVATE.test(config.url ?? '')) {
            return config;
        }

        config.adapter = async () => ({ data: { data: null }, status: 200, statusText: 'OK', headers: {}, config });

        return config;
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) {
            event.preventDefault();
        }
    }, true);

    document.addEventListener('submit', (event) => event.preventDefault(), true);

    /* ---- Stand-ins for the cards' cart and compare ---- */

    const inCart = {};

    globals.$cardCart = {
        load: () => Promise.resolve(),

        refresh() {},

        quantity: (productId) => inCart[productId] ?? 0,

        async add(product) {
            await new Promise((resolve) => setTimeout(resolve, 450));

            inCart[product.id] = 1;

            emitter.emit('card-cart-changed');

            return true;
        },

        async setQuantity(productId, quantity) {
            inCart[productId] = Math.max(0, quantity);

            emitter.emit('card-cart-changed');

            return { outcome: 'done' };
        },

        remove(productId) {
            return this.setQuantity(productId, 0);
        },
    };

    const limit = globals.$compareList?.limit ?? 4;

    globals.$cardCompare = {
        limit,

        ids: [],

        products: {},

        cards: 0,

        load: () => Promise.resolve(),

        refresh() {},

        has(productId) {
            return this.ids.includes(productId);
        },

        async add(product) {
            if (! this.has(product.id)) {
                this.products[product.id] = { id: product.id, name: product.name, image: product.base_image?.small_image_url };

                this.ids = [...this.ids, product.id];

                this.changed();
            }
        },

        async remove(productId) {
            this.ids = this.ids.filter((id) => id !== productId);

            this.changed();
        },

        async clear() {
            this.ids = [];

            this.changed();
        },

        cardMounted() {
            this.cards++;

            this.changed();
        },

        cardUnmounted() {
            this.cards--;

            this.changed();
        },

        changed() {
            emitter.emit('card-compare-changed', { ids: [...this.ids], cards: this.cards });
        },
    };

    globals.$compareList = {
        limit,

        guestIds: () => [],

        addForGuest: () => 'added',

        removeForGuest() {},

        clearForGuest() {},

        handleLimitError: () => false,

        changed() {},

        showLimit() {},
    };

    /* ---- The loop ---- */

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    let onScreen = true;

    // The first styled card, once the page has started (Classic cards have no motion of their own).
    let card = null;

    // What wait() is waiting on while the demo is paused or still.
    let resumers = [];

    const playing = () => !! card && onScreen && ! document.hidden && ! reducedMotion.matches;

    /**
     * Show the state on <html data-demo>, and wake any wait() once the demo may play again.
     */
    const showState = () => {
        root.dataset.demo = ! card || reducedMotion.matches ? 'still' : (playing() ? 'playing' : 'paused');

        if (playing()) {
            resumers.splice(0).forEach((resume) => resume());
        }
    };

    const whenPlaying = () => (playing() ? Promise.resolve() : new Promise((resume) => resumers.push(resume)));

    /**
     * Wait `ms`, counting only the time the demo may play: while the preview is off screen, the tab is
     * hidden or motion is reduced, it simply waits to be woken (no timer runs).
     */
    const wait = async (ms) => {
        for (let left = ms; left > 0; left -= 250) {
            await whenPlaying();

            await new Promise((resolve) => setTimeout(resolve, Math.min(left, 250)));
        }
    };

    const press = (element, key) => element?.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));

    const click = (element) => element?.click();

    /**
     * The photos turn once round, back to the first.
     */
    async function turnPhotos(card) {
        const count = card.querySelectorAll('.pc-photo').length;

        if (count < 2) {
            return;
        }

        const next = root.dir === 'rtl' ? 'ArrowLeft' : 'ArrowRight';

        for (let step = 0; step < count; step++) {
            await wait(step ? 1300 : 900);

            card.classList.contains('pc-card--deck')
                ? click(card.querySelector('.pc-deck-next'))
                : press(card.querySelector('.pc-media-link'), next);
        }
    }

    /**
     * Add to cart, then + and − back to Add to cart.
     */
    async function buy(card) {
        const add = card.querySelector('.pc-add');

        if (! add || add.disabled) {
            return;
        }

        await wait(1000);
        click(add);

        await wait(2200);
        click(card.querySelector('.pc-stepper button:last-child'));

        await wait(1300);
        click(card.querySelector('.pc-stepper button:first-child'));

        await wait(1100);
        click(card.querySelector('.pc-stepper button:first-child'));
    }

    /**
     * Compare ticks, then unticks.
     */
    async function compare(card) {
        const button = card.querySelector('.pc-compare');

        if (! button) {
            return;
        }

        await wait(1300);
        click(button);

        await wait(1500);
        click(button);
    }

    async function play(card) {
        for (;;) {
            await turnPhotos(card);
            await buy(card);
            await compare(card);
            await wait(1600);
        }
    }

    window.addEventListener('load', () => setTimeout(() => {
        card = document.querySelector('.pc-card');

        reducedMotion.addEventListener('change', showState);

        showState();

        if (! card) {
            return;
        }

        new IntersectionObserver(([entry]) => {
            onScreen = entry.isIntersecting;

            showState();
        }).observe(card);

        document.addEventListener('visibilitychange', showState);

        play(card);
    }));
</script>
