{{--
    Copy for the $cardGallery mixin (assets/js/plugins/card-gallery.js), which the card photo
    components share. Provided app-wide and injected when a component is created, so it does not
    matter where Blade puts this push on the page. Pushed once per page.
--}}
@pushOnce('scripts')
    <script type="module">
        app.provide('cardGalleryText', {{ Js::from([
            'photoOf' => trans('shop::app.components.products.card.photo-of'),
        ]) }});
    </script>
@endPushOnce
