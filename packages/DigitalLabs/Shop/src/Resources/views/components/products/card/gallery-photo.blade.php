{{--
    One photo inside a card photo component's template. State (requested, loaded) comes from the
    $cardGallery mixin (assets/js/plugins/card-gallery.js); `photo` and `index` from the surrounding
    v-for. Photo 1 is the card's image: it carries the product name and loads lazily like today's
    card. The others are decorative (alt="") and get a src only once the component wakes them.
--}}
<div
    class="pc-photo"
    :class="{ 'is-loaded': loaded[index] }"
>
    <img
        :src="requested[index] ? photo.medium_image_url : null"
        :srcset="requested[index] ? `${photo.small_image_url} 150w, ${photo.medium_image_url} 300w` : null"
        sizes="(max-width: 768px) 150px, (max-width: 1200px) 300px, 600px"
        :alt="index === 0 ? product.name : ''"
        :loading="index === 0 ? 'lazy' : null"
        width="291"
        height="300"
        decoding="async"
        draggable="false"
        @load="loaded[index] = true"
    >
</div>
