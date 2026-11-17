import { fillPlaceholders } from "./fill-placeholders";

/**
 * The logic every product card photo component shares (the Swipe card's v-card-swipe-gallery, the
 * Photo deck card's v-card-deck-gallery), as a Vue mixin. Components defined in Blade use it as
 * `mixins: [app.config.globalProperties.$cardGallery]`. It lives in the bundle, not in a Blade push,
 * because Blade does not keep pushes from nested views in page order, and a mixin must exist when
 * the component is defined. It gives the component:
 *   - props `product` and `url`, and `photos` (the product's images, or its base image);
 *   - lazy loading: photo 1 loads like today's card, the others once `wake()` is called (pointing,
 *     focus, touch) or, on touch screens, once the card scrolls into view;
 *   - `reset()` when a grid reuses the component for another product (filters, paging);
 *   - the keyboard hint id, the "Photo 2 of 4" label and the live-region announcement;
 *   - `arrowStep()` for the left and right arrow keys, mirrored right-to-left.
 *
 * The markup that goes with it is the Blade partials card/gallery-photo (one photo) and
 * card/gallery-hint (the hint and the live region); card/gallery-text provides its copy. A component
 * adds its own state through two hooks: onReset() and, on touch screens only, onCardInView(entry).
 */
export const cardGallery = {
    inject: {
        /*
         * { photoOf: "Photo :current of :total" }, provided by card/gallery-text.
         */
        galleryText: "cardGalleryText",
    },

    props: {
        product: {
            type: Object,
            required: true,
        },

        url: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            /*
             * Per photo: asked to load (it has a src), and finished loading.
             */
            requested: [],

            loaded: [],

            announcement: "",

            /*
             * Same split as product-cards.css: a computer with a mouse, or a touch screen.
             */
            hasMouse: window.matchMedia("(hover: hover) and (pointer: fine)").matches,

            isRtl: document.documentElement.dir === "rtl",
        };
    },

    computed: {
        photos() {
            return this.product.images?.length ? this.product.images : [this.product.base_image];
        },

        hintId() {
            return `pc-photo-hint-${this.$.uid}`;
        },
    },

    watch: {
        /*
         * Grids reuse card components when their product list changes (filters, paging).
         */
        "product.id"() {
            this.reset();
        },
    },

    created() {
        this.reset();
    },

    mounted() {
        if (this.hasMouse || this.photos.length < 2) {
            return;
        }

        // Touch screens: get photo 2 ready as the card scrolls into view.
        this.observer = new IntersectionObserver((entries) => {
            const entry = entries[0];

            if (! entry.isIntersecting) {
                return;
            }

            this.wake(1);

            this.onCardInView(entry);
        }, { threshold: [0, 0.65] });

        this.observer.observe(this.$el);
    },

    beforeUnmount() {
        this.observer?.disconnect();
    },

    methods: {
        reset() {
            this.requested = this.photos.map((photo, index) => index === 0);

            this.loaded = [];

            this.announcement = "";

            this.onReset();
        },

        /**
         * Hook: the component's own state back to photo 1.
         */
        onReset() {},

        /**
         * Hook, touch screens only: the card is (more) in view; entry.intersectionRatio says how much.
         */
        onCardInView(entry) {},

        /**
         * Start loading photos up to index `upto` (all of them by default).
         */
        wake(upto = Infinity) {
            this.requested = this.photos.map((photo, index) => this.requested[index] || index <= upto);
        },

        /**
         * "Photo 2 of 4" for the photo at `index`.
         */
        photoLabel(index) {
            return fillPlaceholders(this.galleryText.photoOf, { current: index + 1, total: this.photos.length });
        },

        /**
         * Tell screen readers which photo shows now (the live region in card/gallery-hint).
         */
        announce(index) {
            this.announcement = this.photoLabel(index);
        },

        /**
         * 1 for the arrow key that means "next photo", -1 for "previous", 0 for any other key or a
         * single photo. Right is next in English, left is next in Arabic. The arrows' page scroll is
         * stopped when they change the photo.
         */
        arrowStep(event) {
            if (this.photos.length < 2 || ! ["ArrowLeft", "ArrowRight"].includes(event.key)) {
                return 0;
            }

            event.preventDefault();

            return (event.key === "ArrowRight") !== this.isRtl ? 1 : -1;
        },

        prefersReducedMotion() {
            return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        },
    },
};

export default {
    install(app) {
        app.config.globalProperties.$cardGallery = cardGallery;
    },
};
