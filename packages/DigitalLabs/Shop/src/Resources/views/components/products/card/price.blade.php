{!! view_render_event('qubix.shop.components.products.card.price.before') !!}

{{-- Same price HTML as the Classic card; product-cards.css puts the current price first. --}}
<div
    class="pc-price"
    v-html="product.price_html"
>
</div>

{!! view_render_event('qubix.shop.components.products.card.price.after') !!}
