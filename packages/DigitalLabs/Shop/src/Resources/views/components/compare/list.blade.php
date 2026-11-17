{{--
    $compareList: the shop's one client-side compare helper. A compare list holds at most
    config('shop.compare.limit') products (owner decision, 2026-11-16).
    - Guests keep their list in localStorage `compare_items`. Every card and the product page read
      and write it only through guestIds(), addForGuest(), removeForGuest() and clearForGuest();
      addForGuest() is the only place that checks the limit for them. (The compare page keeps its
      own copy of this code.)
    - Signed-in customers are held to it by the compare endpoint; handleLimitError() turns its refusal
      into the same message.
    The message links to the compare page. Used by the Classic card, the product page and the
    Swipe/Photo deck cards. Pushed once per page.
--}}
@pushOnce('scripts')
    {{-- Built here because @json() splits its argument on commas, so it can't take an array literal. --}}
    @php
        $compareLimit = (int) config('shop.compare.limit');

        $compareLimitText = [
            'message' => trans('shop::app.compare.limit-reached', ['max' => $compareLimit]),
            'link' => trans('shop::app.compare.limit-link'),
            'url' => route('shop.compare.index'),
        ];
    @endphp

    <script type="module">
        const limit = @json($compareLimit);

        const limitText = @json($compareLimitText);

        const emitter = app.config.globalProperties.$emitter;

        app.config.globalProperties.$compareList = {
            limit,

            guestIds() {
                return JSON.parse(localStorage.getItem('compare_items') ?? '[]');
            },

            /**
             * Add to a guest's list: 'added', 'already', or 'full' (the limit message is shown here).
             */
            addForGuest(productId) {
                const ids = this.guestIds();

                if (ids.includes(productId)) {
                    return 'already';
                }

                if (ids.length >= limit) {
                    this.showLimit();

                    return 'full';
                }

                localStorage.setItem('compare_items', JSON.stringify([...ids, productId]));

                return 'added';
            },

            /**
             * Take a product off a guest's list.
             */
            removeForGuest(productId) {
                localStorage.setItem('compare_items', JSON.stringify(this.guestIds().filter(id => id != productId)));
            },

            /**
             * Empty a guest's list.
             */
            clearForGuest() {
                localStorage.removeItem('compare_items');
            },

            /**
             * True (and the limit message shown) when the compare endpoint refused a customer's add for the limit.
             */
            handleLimitError(error) {
                if (error?.response?.data?.data?.reason !== 'compare-limit') {
                    return false;
                }

                this.showLimit();

                return true;
            },

            showLimit() {
                emitter.emit('add-flash', {
                    type: 'warning',
                    message: limitText.message,
                    link: { url: limitText.url, label: limitText.link },
                });
            },
        };
    </script>
@endPushOnce
