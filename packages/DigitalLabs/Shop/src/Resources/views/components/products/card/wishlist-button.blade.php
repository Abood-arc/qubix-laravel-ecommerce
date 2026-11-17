{{--
    Wishlist heart. $variant: 'tool' (a round button on the photo, Swipe card) or 'inline'
    (an outlined button beside the price, Photo deck card).
--}}
{!! view_render_event('qubix.shop.components.products.card.wishlist_option.before') !!}

@if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
    <button
        type="button"
        class="pc-wish {{ $variant === 'tool' ? 'pc-tool' : 'pc-icon-button' }}"
        :class="{ 'is-popping': wishlistPopping }"
        :aria-pressed="product.is_wishlist ? 'true' : 'false'"
        :aria-label="text('saveToWishlist', { name: product.name })"
        @click="addToWishlist()"
    >
        <x-shop::products.card.icon name="heart" />

        <x-shop::products.card.icon
            name="heart"
            class="pc-heart-fill"
        />

        <span
            class="pc-burst"
            aria-hidden="true"
        ><i></i><i></i><i></i><i></i><i></i><i></i></span>
    </button>
@endif

{!! view_render_event('qubix.shop.components.products.card.wishlist_option.after') !!}
