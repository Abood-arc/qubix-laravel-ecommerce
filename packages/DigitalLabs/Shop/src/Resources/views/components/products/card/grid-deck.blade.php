{{--
    Grid card, "Photo deck" (design concept E). On the photo: the stack of photos with its "1 / 4"
    counter, the discount badge and a round bag button that adds to cart. Below it: the name, the
    price with the wishlist and compare buttons beside it, and the low-stock line. No full-width
    button, so cards are shorter and more fit on a phone screen.
--}}
<!-- Grid Card -->
<article
    class="pc-card pc-card--deck"
    data-card-style="deck"
    v-if="mode != 'list'"
>
    <x-shop::products.card.deck-gallery
        ::product="product"
        ::url="productUrl"
    >
        {!! view_render_event('qubix.shop.components.products.card.image.before') !!}

        @include('shop::components.products.card.badge')

        <x-shop::products.card.buy
            ::product="product"
            variant="round"
        />

        {!! view_render_event('qubix.shop.components.products.card.image.after') !!}
    </x-shop::products.card.deck-gallery>

    <div class="pc-info">
        @include('shop::components.products.card.name')

        <div class="pc-price-row">
            @include('shop::components.products.card.price')

            @include('shop::components.products.card.wishlist-button', ['variant' => 'inline'])

            <x-shop::products.card.compare
                ::product="product"
                variant="icon"
            />
        </div>

        @include('shop::components.products.card.ratings')

        @include('shop::components.products.card.stock')
    </div>
</article>
