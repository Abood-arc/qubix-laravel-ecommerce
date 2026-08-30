@php
    $channel = core()->getCurrentChannel();
    $isHeroCarouselRendered = false;
    $homeLayoutMode = config('shop.home.layout_mode', 'legacy');
@endphp

<!-- SEO Meta Content -->
@push ('meta')
    <meta
        name="title"
        content="{{ $channel->home_seo['meta_title'] ?? '' }}"
    />

    <meta
        name="description"
        content="{{ $channel->home_seo['meta_description'] ?? '' }}"
    />

    <meta
        name="keywords"
        content="{{ $channel->home_seo['meta_keywords'] ?? '' }}"
    />

    @if ($homeLayoutMode === 'v2')
        {{-- Visible in DevTools / View Source; not shown on screen --}}
        <meta name="velocity-home-layout" content="v2">
    @endif
@endPush

@push('scripts')
    @if(! empty($categories))
        <script>
            localStorage.setItem('categories', JSON.stringify(@json($categories)));
        </script>
    @endif
@endpush

@push('scripts')
    <script>
        (function () {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            /*
             * Homepage sections (everything except .home-hero-section) fade + slide
             * up when they enter the viewport.
             *
             * Why not IntersectionObserver / one-shot timeouts:
             *   Product & category carousels are Vue components that swap shimmer
             *   → API-loaded markup after mount.  Layout height changes at
             *   unpredictable times.  A single measurement after window.load or
             *   setInterval(150 ms) often builds an empty watch list or fires
             *   too early, so nothing appears to animate.
             *
             * Strategy (same spirit as the sticky-header fix):
             *   • requestAnimationFrame loop — reads getBoundingClientRect every
             *     frame; scroll container quirks do not matter.
             *   • MutationObserver on .home-premium — new sections from dynamic
             *     HTML still get picked up.
             *   • Per-section state machine: pending → hidden (lower viewport /
             *     fully below) → revealed when intersecting viewport.
             *   • If JS never runs, sections are never pre-hidden (progressive
             *     enhancement).
             */
            var selector = '.home-premium section:not(.home-hero-section)';
            var tracked  = new Set();
            var phase    = new Map(); /* el → 'pending' | 'hidden' */

            function discover() {
                document.querySelectorAll(selector).forEach(function (el) {
                    if (! tracked.has(el)) {
                        tracked.add(el);
                        phase.set(el, 'pending');
                    }
                });
            }

            function scrub(el) {
                tracked.delete(el);
                phase.delete(el);
            }

            function reveal(el) {
                scrub(el);

                el.style.transition = 'opacity 0.7s ease, transform 0.85s cubic-bezier(0.16, 1, 0.3, 1)';

                requestAnimationFrame(function () {
                    el.style.opacity   = '1';
                    el.style.transform = 'translateY(0)';
                });

                window.setTimeout(function () {
                    el.style.transition = '';
                    el.style.opacity    = '';
                    el.style.transform  = '';
                }, 950);
            }

            function forceShow(el) {
                el.style.opacity   = '';
                el.style.transform = '';
                el.style.transition = '';
                scrub(el);
            }

            function tick() {
                discover();

                var vh = window.innerHeight;

                Array.from(tracked).forEach(function (el) {
                    if (! el.isConnected) {
                        scrub(el);
                        return;
                    }

                    var st = phase.get(el);

                    /* Already animated */
                    if (! st) return;

                    var rect = el.getBoundingClientRect();

                    /*
                     * Wait for real layout (Vue replaced shimmer, images, etc.).
                     * Threshold intentionally low — only skip completely flat nodes.
                     */
                    if (st === 'pending' && rect.height < 4) return;

                    if (st === 'pending') {
                        /*
                         * "Below the fold" for editorial/home layouts:
                         * Product sections often sit partly inside the viewport
                         * (hero is not always full-screen).  Using only
                         * rect.top > innerHeight skips them entirely — no animation.
                         *
                         * Hide when the section's top sits in the lower portion of
                         * the viewport OR is completely beneath it.  Already well
                         * inside the upper viewport → leave untouched (no blanking).
                         */
                        var hideThreshold = vh * 0.82;

                        if (rect.top > hideThreshold || rect.top > vh + 2) {
                            el.style.opacity   = '0';
                            el.style.transform = 'translateY(52px)';
                            phase.set(el, 'hidden');
                        } else {
                            scrub(el);
                        }

                        return;
                    }

                    if (st === 'hidden') {
                        var intersects =
                            rect.top < vh * 0.93 &&
                            rect.bottom > 56 &&
                            rect.height >= 4;

                        if (intersects) reveal(el);
                    }
                });

                requestAnimationFrame(tick);
            }

            window.addEventListener('load', function () {
                var root = document.querySelector('.home-premium');

                if (root) {
                    new MutationObserver(function () {
                        discover();
                    }).observe(root, { childList: true, subtree: true });
                }

                /*
                 * Two rAF delays: Vue app.mount('#app') runs on this same load
                 * event — wait until after its synchronous patch cycle before
                 * our first layout reads matter.
                 */
                requestAnimationFrame(function () {
                    requestAnimationFrame(tick);
                });

                /* Absolute safety net — never leave sections invisible */
                window.setTimeout(function () {
                    Array.from(tracked).forEach(forceShow);
                }, 14000);
            });
        })();
    </script>
@endpush

<x-shop::layouts>
    <!-- Page Title -->
    <x-slot:title>
        {{  $channel->home_seo['meta_title'] ?? '' }}
    </x-slot>

    <div class="home-premium bg-[#faf9f7] pb-10">
        <!-- Loop over the theme customization -->
        @foreach ($customizations as $customization)
            @php ($data = $customization->options) @endphp

            <!-- Static content -->
            @switch ($customization->type)
                @case ($customization::IMAGE_CAROUSEL)
                    @if (! $isHeroCarouselRendered)
                        <section class="home-hero-section pt-0">
                            <div class="w-full overflow-hidden bg-white shadow-[0_24px_50px_rgba(15,23,42,0.1)]">
                                <x-shop::carousel
                                    :options="$data"
                                    aria-label="{{ trans('shop::app.home.index.image-carousel') }}"
                                />
                            </div>
                        </section>
                    @else
                        <section class="mt-8">
                            <x-shop::carousel
                                :options="$data"
                                aria-label="{{ trans('shop::app.home.index.image-carousel') }}"
                            />
                        </section>
                    @endif

                    @php
                        $isHeroCarouselRendered = true;
                    @endphp
                    @break
                @case ($customization::STATIC_CONTENT)
                    @if ($homeLayoutMode !== 'v2')
                        <!-- push style -->
                        @if (! empty($data['css']))
                            @push ('styles')
                                <style>
                                    {{ $data['css'] }}
                                </style>
                            @endpush
                        @endif

                        <!-- render html -->
                        @if (! empty($data['html']))
                            <section class="mx-auto mt-10 max-w-[1440px] px-3 md:px-6">
                                {!! str_replace(
                                    ['src=""', 'data-src', 'src="storage/theme/'],
                                    ['', 'src', 'src="'.config('app.url').'/storage/theme/'],
                                    $data['html']
                                ) !!}
                            </section>
                        @endif
                    @endif

                    @break
                @case ($customization::CATEGORY_CAROUSEL)
                    @if ($homeLayoutMode !== 'v2')
                        <!-- Categories carousel -->
                        <section class="mx-auto mt-12 max-w-[1440px] px-3 md:px-6">
                            <x-shop::categories.carousel
                                :title="$data['title'] ?? ''"
                                :src="route('shop.api.categories.index', $data['filters'] ?? [])"
                                :navigation-link="route('shop.home.index')"
                                aria-label="{{ trans('shop::app.home.index.categories-carousel') }}"
                            />
                        </section>
                    @endif

                    @break
                @case ($customization::PRODUCT_CAROUSEL)
                    @if ($homeLayoutMode !== 'v2')
                        <!-- Product Carousel -->
                        <section class="mx-auto mt-12 max-w-[1440px] px-3 md:px-6">
                            <x-shop::products.carousel
                                :title="$data['title'] ?? ''"
                                :src="route('shop.api.products.index', $data['filters'] ?? [])"
                                :navigation-link="route('shop.search.index', $data['filters'] ?? [])"
                                aria-label="{{ trans('shop::app.home.index.product-carousel') }}"
                            />
                        </section>
                    @endif

                    @break
            @endswitch
        @endforeach

        @if ($homeLayoutMode === 'v2')
            @include('shop::home.v2-placeholder')
        @endif
    </div>
</x-shop::layouts>
