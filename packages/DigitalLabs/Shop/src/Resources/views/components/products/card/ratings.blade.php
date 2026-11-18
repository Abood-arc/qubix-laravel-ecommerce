{!! view_render_event('qubix.shop.components.products.card.average_ratings.before') !!}

@if (core()->getConfigData('catalog.products.review.summary') == 'star_counts')
    <x-shop::products.ratings
        class="pc-ratings"
        ::average="product.ratings.average"
        ::total="product.ratings.total"
        ::rating="false"
        v-if="product.ratings.total"
    />
@else
    <x-shop::products.ratings
        class="pc-ratings"
        ::average="product.ratings.average"
        ::total="product.reviews.total"
        ::rating="false"
        v-if="product.reviews.total"
    />
@endif

{!! view_render_event('qubix.shop.components.products.card.average_ratings.after') !!}
