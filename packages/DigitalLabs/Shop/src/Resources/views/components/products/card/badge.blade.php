{{-- The discount worked out from the real prices; otherwise Sale or New, as on the Classic card. --}}
<span
    class="pc-badge"
    aria-hidden="true"
    v-if="discountPercent"
>
    <bdi dir="ltr">−@{{ discountPercent }}%</bdi>
</span>

<span
    class="pc-badge"
    v-else-if="product.on_sale"
>
    @lang('shop::app.components.products.card.sale')
</span>

<span
    class="pc-badge pc-badge--new"
    v-else-if="product.is_new"
>
    @lang('shop::app.components.products.card.new')
</span>

<span
    class="sr-only"
    v-if="discountPercent"
    v-text="text('discount', { percent: discountPercent })"
></span>
