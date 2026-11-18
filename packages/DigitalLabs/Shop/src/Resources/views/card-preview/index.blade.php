{{--
    An admin's live preview (CardPreviewController): the store's real product card, in the style and
    look being previewed, with two of the store's products, side by side as on a phone. With the store
    look's preview it sits under the store's real header. The demo (card/demo) plays on a loop.
--}}
<x-shop::layouts.preview
    :look="$look"
    :has-header="$withHeader"
>
    @if (count($products))
        <v-card-preview :products="{{ Js::from($products) }}"></v-card-preview>
    @else
        <p class="px-4 py-10 text-center text-sm text-zinc-500">
            @lang('shop::app.card-preview.no-products')
        </p>
    @endif

    @pushOnce('scripts')
        {{-- The grid the home page's product rows use (products/carousel). --}}
        <script
            type="text/x-template"
            id="v-card-preview-template"
        >
            <div class="container mt-5 max-lg:px-8 max-sm:!px-4">
                <div class="grid grid-cols-4 justify-items-center gap-x-6 gap-y-10 max-1060:grid-cols-3 max-md:grid-cols-2 max-md:gap-x-4 max-md:gap-y-8">
                    <x-shop::products.card v-for="product in products" />
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-card-preview', {
                template: '#v-card-preview-template',

                props: {
                    products: {
                        type: Array,
                        required: true,
                    },
                },
            });
        </script>
    @endPushOnce
</x-shop::layouts.preview>
