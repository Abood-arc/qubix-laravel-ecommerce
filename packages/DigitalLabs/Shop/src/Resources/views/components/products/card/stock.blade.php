{{-- "Only 3 left" from the channel's stock (ProductResource::stock_left, 1 to 5). --}}
<p
    class="pc-stock"
    v-if="product.stock_left"
    v-text="text('onlyLeft', { count: product.stock_left })"
></p>
