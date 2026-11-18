        {{--
            window.qubixCategoryNav and the category tree stamp ($categoryTreeStamp, passed in), in the
            <head> of both shop layouts (index, preview). Single source of truth for the three
            localStorage['categories'] writers: this stamp, the home page's embedded snapshot
            (home/index.blade.php) and the v-desktop-category / v-mobile-category components in the
            header. Deliberately inline in <head> rather than pushed onto the 'scripts' stack:
            home/index.blade.php's own @push('scripts') call into this object runs *before*
            <x-shop::layouts> is even reached (it's plain Blade above the opening tag), and even a
            component-slot's pushed content is always captured before the component's own template,
            including the header, runs. Either way, anything pushed onto 'scripts' cannot be relied on
            to define this before a page's own pushed scripts use it. <head> always precedes <body> in
            the rendered HTML regardless of Blade's server-side render order, so defining it here is
            what actually guarantees it exists before any consumer's inline script runs.
        --}}
        <script>
            window.qubixCategoryTreeStamp = @json($categoryTreeStamp);

            window.qubixCategoryNav = {
                STORAGE_KEY: 'categories',

                read(currentStamp) {
                    try {
                        const stored = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || 'null');

                        if (
                            stored
                            && Array.isArray(stored.categories)
                            && stored.categories.length > 0
                            && stored.stamp === currentStamp
                        ) {
                            return stored.categories;
                        }
                    } catch (e) {}

                    return null;
                },

                write(categories, stamp) {
                    try {
                        localStorage.setItem(this.STORAGE_KEY, JSON.stringify({ categories, stamp }));
                    } catch (e) {}
                },

                fetchFresh(url) {
                    return axios.get(url).then((response) => ({
                        categories: Array.isArray(response.data.data) ? response.data.data : [],
                        stamp: response.data.stamp ?? null,
                    }));
                },
            };
        </script>
