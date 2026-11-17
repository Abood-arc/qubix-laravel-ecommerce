{{--
    For a card photo component with more than one photo (state from the $cardGallery mixin,
    assets/js/plugins/card-gallery.js): the keyboard hint the photo link points to
    (aria-describedby="hintId"), and the live region that reads out the photo the arrow keys moved
    to. The card's one status region.
--}}
<span
    class="sr-only"
    :id="hintId"
>
    @lang('shop::app.components.products.card.photo-keys-hint')
</span>

<span
    class="sr-only"
    role="status"
    v-text="announcement"
></span>
