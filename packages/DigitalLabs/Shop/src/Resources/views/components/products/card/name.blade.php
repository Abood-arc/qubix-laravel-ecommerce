{!! view_render_event('qubix.shop.components.products.card.name.before') !!}

{{-- The photo link is the card's keyboard stop; the name link repeats it for mouse and touch. --}}
<p
    class="pc-name"
    dir="auto"
>
    <a
        :href="productUrl"
        tabindex="-1"
        v-text="product.name"
    ></a>
</p>

{!! view_render_event('qubix.shop.components.products.card.name.after') !!}
