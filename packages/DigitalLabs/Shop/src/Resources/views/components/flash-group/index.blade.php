<v-flash-group ref='flashes'></v-flash-group>

@pushOnce('scripts')
    @php
        $flashes = [];

        foreach (['success', 'warning', 'error', 'info'] as $type) {
            if (session()->has($type)) {
                $flashes[] = [
                    'type' => $type,
                    'message' => session($type)
                ];
            }
        }

        $isResponseCacheMiddlwareActive = false;

        $currentRoute = request()->route();

        if ($currentRoute) {
            $middlewares = $currentRoute->gatherMiddleware();

            $isResponseCacheMiddlwareActive = in_array('cache.response', $middlewares);
        }
    @endphp

    <script
        type="text/x-template"
        id="v-flash-group-template"
    >
        <transition-group
            tag='div'
            name="flash-group"
            enter-from-class="ltr:translate-x-full rtl:-translate-x-full"
            enter-active-class="transform transition duration-200 ease-in-out"
            enter-to-class="ltr:translate-x-0 rtl:-translate-x-0"
            leave-from-class="ltr:translate-x-0 rtl:-translate-x-0"
            leave-active-class="transform transition duration-200 ease-in-out"
            leave-to-class="ltr:translate-x-full rtl:-translate-x-full"
            class='fixed top-5 z-[1001] grid justify-items-end gap-2.5 max-sm:hidden ltr:right-5 rtl:left-5'
        >
            <x-shop::flash-group.item />
        </transition-group>

        <transition-group
            tag='div'
            name="flash-group"
            enter-from-class="ltr:translate-y-full rtl:-translate-y-full"
            enter-active-class="transform transition duration-200 ease-in-out"
            enter-to-class="ltr:translate-y-0 rtl:-translate-y-0"
            leave-from-class="ltr:translate-y-0 rtl:-translate-y-0"
            leave-active-class="transform transition duration-200 ease-in-out"
            leave-to-class="ltr:translate-y-full rtl:-translate-y-full"
            class='fixed bottom-10 left-1/2 z-[1001] grid -translate-x-1/2 -translate-y-1/2 transform justify-items-center gap-2.5 sm:hidden'
        >
            <x-shop::flash-group.item />
        </transition-group>
    </script>

    <script type="module">
        app.component('v-flash-group', {
            template: '#v-flash-group-template',

            data() {
                return {
                    uid: 0,

                    flashes: []
                }
            },

            created() {
                /*
                 * One auto-close timer per flash, by uid. The group owns them because every flash is
                 * shown twice (the desktop and the phone list), so a timer per shown copy would close
                 * it twice.
                 */
                this.timers = new Map();

                this.loadInitialFlashes();

                this.registerGlobalEvents();
            },

            beforeUnmount() {
                this.$emitter.off('add-flash', this.add);

                this.timers.forEach(timer => clearTimeout(timer));

                this.timers.clear();
            },

            methods: {
                loadInitialFlashes() {
                    @if (
                        config('responsecache.enabled') 
                        && $isResponseCacheMiddlwareActive
                    )
                        let flashes = '<qubix-response-cache-session-flashes>';
                    @else
                        let flashes = @json($flashes);
                    @endif

                    if (typeof(flashes) === 'string') {
                        return;
                    }
                    
                    flashes.forEach(flash => this.add(flash));
                },

                add(flash) {
                    /*
                     * A message with a link stays until closed, so the same one added again (the compare
                     * limit refused on every click) would only pile up. It is already showing: skip it.
                     * Messages without a link close themselves and are added as before.
                     */
                    if (flash.link && this.flashes.some(item => item.link && item.message === flash.message)) {
                        return;
                    }

                    flash.uid = this.uid++;

                    this.flashes.push(flash);

                    this.scheduleClose(flash);
                },

                /**
                 * A message closes itself after a second. A message with a link (flash.link) waits for
                 * the shopper to close it: following the link needs time (WCAG 2.2.1).
                 */
                scheduleClose(flash) {
                    if (flash.link) {
                        return;
                    }

                    this.timers.set(flash.uid, setTimeout(() => this.remove(flash), 1000));
                },

                remove(flash) {
                    clearTimeout(this.timers.get(flash.uid));

                    this.timers.delete(flash.uid);

                    const index = this.flashes.findIndex(item => item.uid === flash.uid);

                    // Already gone (closed by hand, or from the other copy): never remove another message.
                    if (index !== -1) {
                        this.flashes.splice(index, 1);
                    }
                },

                registerGlobalEvents() {
                    this.$emitter.on('add-flash', this.add);
                },
            }
        });
    </script>
@endpushOnce
