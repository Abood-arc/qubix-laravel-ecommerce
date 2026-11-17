{{-- Size line from the Width, Height and Length attributes; hidden while they are empty. --}}
<p
    class="pc-size"
    v-if="product.dimensions"
>
    <x-shop::products.card.icon name="ruler" />

    <span
        class="sr-only"
        v-text="sizeSpoken"
    ></span>

    <span aria-hidden="true">
        <bdi
            dir="ltr"
            v-text="sizeNumbers"
        ></bdi>
        @lang('shop::app.components.products.card.unit-'.$sizeUnit)
    </span>
</p>
