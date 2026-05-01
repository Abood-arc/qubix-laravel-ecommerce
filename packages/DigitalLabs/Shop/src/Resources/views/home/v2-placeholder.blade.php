{{--
    Velocity home layout v2 — empty shell for upcoming catalog-style module.
    Extend via view_render_event listeners or by replacing this partial later.
    Do not remove hooks; admin custom CSS/JS stays on the main layout.
--}}

{!! view_render_event('qubix.shop.home.v2.before') !!}

@php
    $v2Products = [
        [
            'name' => 'Golden Artisan Glass',
            'price' => 'Rs. 1,299',
            'image' => asset('images/home-v2/product-1.png'),
        ],
        [
            'name' => 'Handcrafted Steel Glass',
            'price' => 'Rs. 999',
            'image' => asset('images/home-v2/product-2.png'),
        ],
        [
            'name' => 'Peacock Gift Glass',
            'price' => 'Rs. 1,199',
            'image' => asset('images/home-v2/product-3.png'),
        ],
        [
            'name' => 'Decorative Serving Tray',
            'price' => 'Rs. 1,899',
            'image' => asset('images/home-v2/product-4.png'),
        ],
    ];
@endphp

<div
    id="velocity-home-layout-v2"
    class="velocity-home-layout-v2 mx-auto max-w-[1440px] px-3 py-6 md:px-6 md:py-8"
    data-home-layout="v2"
    data-velocity-home-layout-version="v2"
>
    <section class="velocity-v2-hero overflow-hidden rounded-[26px] bg-[#f6f3ee]" aria-label="Featured collection">
        <div class="grid items-center gap-6 lg:grid-cols-[1.05fr_1fr]">
            <div class="px-6 py-8 md:px-10 md:py-12">
                <p class="velocity-v2-kicker">Modern Heritage Collection</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#1f2b25] md:text-5xl">
                    Handcrafted Decor
                    <span class="block text-[#1f6b56]">for Contemporary Homes</span>
                </h2>

                <p class="mt-4 max-w-xl text-sm leading-7 text-zinc-600 md:text-base">
                    Thoughtfully designed giftware and table pieces made to elevate daily living and festive moments.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('shop.search.index') }}" class="velocity-v2-btn-primary">
                        Shop Collection
                    </a>

                    <a href="{{ route('shop.home.contact_us') }}" class="velocity-v2-btn-secondary">
                        Corporate Orders
                    </a>
                </div>
            </div>

            <div class="velocity-v2-hero-media p-4 md:p-6 lg:p-0">
                <img
                    src="{{ asset('images/home-v2/hero.png') }}"
                    alt="Featured handcrafted products"
                    class="h-full w-full object-cover"
                    loading="eager"
                    fetchpriority="high"
                >
            </div>
        </div>
    </section>

    <section class="velocity-v2-trust mt-8 rounded-2xl border border-[#e7ece8] bg-white p-5 md:p-7" aria-label="Store highlights">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <article class="velocity-v2-trust-item">
                <h3>Authentic Craftsmanship</h3>
                <p>Hand-finished by skilled artisans with premium detailing.</p>
            </article>
            <article class="velocity-v2-trust-item">
                <h3>Curated Gifting</h3>
                <p>Unique products suitable for celebrations and premium gifting.</p>
            </article>
            <article class="velocity-v2-trust-item">
                <h3>Secure Packaging</h3>
                <p>Protective packing to keep each product safe in transit.</p>
            </article>
            <article class="velocity-v2-trust-item">
                <h3>Fast Support</h3>
                <p>Quick response for custom orders and product assistance.</p>
            </article>
        </div>
    </section>

    <section class="mt-10" aria-label="Featured products">
        <div class="mb-5 flex items-end justify-between gap-3">
            <div>
                <p class="velocity-v2-kicker">Featured Picks</p>
                <h3 class="mt-1 text-2xl font-semibold text-[#1f2b25] md:text-3xl">Shop the Signature Range</h3>
            </div>

            <a href="{{ route('shop.search.index') }}" class="text-sm font-medium text-[#1f6b56] hover:text-[#154e40]">
                View all products
            </a>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($v2Products as $product)
                <article class="velocity-v2-product-card">
                    <div class="velocity-v2-product-image-wrap">
                        <img
                            src="{{ $product['image'] }}"
                            alt="{{ $product['name'] }}"
                            class="velocity-v2-product-image"
                            loading="lazy"
                        >
                    </div>

                    <div class="mt-4">
                        <h4 class="line-clamp-2 text-sm font-medium text-zinc-800 md:text-base">{{ $product['name'] }}</h4>
                        <p class="mt-2 text-sm font-semibold text-[#1f6b56]">{{ $product['price'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>

{!! view_render_event('qubix.shop.home.v2.after') !!}
