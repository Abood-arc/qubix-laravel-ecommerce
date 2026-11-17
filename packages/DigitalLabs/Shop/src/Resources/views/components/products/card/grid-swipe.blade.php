{{--
    Grid card, "Swipe, named and sized" (design concept G). On the photo: every photo by swipe,
    scrub or arrow keys, the discount badge and the wishlist heart. Below it: the name, the size,
    the price, the low-stock line, a full-width Add to cart and a "Compare" tick box.
--}}
<!-- Grid Card -->
<article
    class="pc-card pc-card--swipe"
    data-card-style="swipe"
    v-if="mode != 'list'"
>
    <x-shop::products.card.swipe-gallery
        ::product="product"
        ::url="productUrl"
    >
        {!! view_render_event('qubix.shop.components.products.card.image.before') !!}

        @include('shop::components.products.card.badge')

        <div class="pc-tools">
            @include('shop::components.products.card.wishlist-button', ['variant' => 'tool'])
        </div>

        {!! view_render_event('qubix.shop.components.products.card.image.after') !!}
    </x-shop::products.card.swipe-gallery>

    <div class="pc-info">
        @include('shop::components.products.card.name')

        @include('shop::components.products.card.size')

        @include('shop::components.products.card.price')

        @include('shop::components.products.card.ratings')

        @include('shop::components.products.card.stock')

        <x-shop::products.card.buy
            ::product="product"
            variant="full"
        />

        <x-shop::products.card.compare
            ::product="product"
            variant="checkbox"
        />
    </div>
</article>
