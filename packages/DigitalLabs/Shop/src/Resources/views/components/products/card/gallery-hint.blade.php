{{--
    For a card photo component (state from the $cardGallery mixin, assets/js/plugins/card-gallery.js):
    with more than one photo, the keyboard hint the photo link points to (aria-describedby="hintId");
    always, the card's one live region. It reads out the photo the arrow keys moved to, and what the
    card's other parts send with $cardAnnounce (Add to cart, the − 1 + control).
--}}
<span
    class="sr-only"
    :id="hintId"
    v-if="photos.length > 1"
>
    @lang('shop::app.components.products.card.photo-keys-hint')
</span>

<span
    class="sr-only"
    role="status"
    v-text="announcement"
></span>
