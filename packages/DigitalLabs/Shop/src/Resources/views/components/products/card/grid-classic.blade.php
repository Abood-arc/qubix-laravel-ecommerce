{{-- Classic grid card: today's card, unchanged. Guarded by tests/Feature/ProductCard/ClassicCardTest.php. --}}
        <!-- Grid Card -->
        <div
            class="group w-full rounded-xl 1180:relative 1180:grid 1180:content-start 1180:overflow-hidden 1180:transition-[transform,box-shadow] 1180:duration-200 1180:ease-[cubic-bezier(0.23,1,0.32,1)] 1180:hover:-translate-y-1 1180:hover:shadow-[0_20px_48px_rgba(0,0,0,0.13)]"
            v-if="mode != 'list'"
        >
            <div class="relative max-h-[300px] max-w-[291px] overflow-hidden rounded-t-xl max-md:max-h-60 max-md:max-w-full max-md:rounded-lg max-sm:max-h-[200px] max-sm:max-w-full">
                {!! view_render_event('qubix.shop.components.products.card.image.before') !!}

                <!-- Product Image -->
                <a
                    :href="`{{ route('shop.product_or_category.index', '') }}/${product.url_key}`"
                    :aria-label="product.name + ' '"
                >
                    <x-shop::media.images.lazy
                            class="after:content-[' '] relative bg-sand transition-transform duration-300 ease-[cubic-bezier(0.23,1,0.32,1)] after:block after:pb-[calc(100%+9px)] 1180:group-hover:scale-105"
                        ::src="product.base_image.medium_image_url"
                        ::srcset="`
                            ${product.base_image.small_image_url} 150w,
                            ${product.base_image.medium_image_url} 300w,
                        `"
                        sizes="(max-width: 768px) 150px, (max-width: 1200px) 300px, 600px"
                        ::key="product.id"
                        ::index="product.id"
                        width="291"
                        height="300"
                        ::alt="product.name"
                    />
                </a>

                {!! view_render_event('qubix.shop.components.products.card.image.after') !!}

                <!-- Product Ratings -->
                {!! view_render_event('qubix.shop.components.products.card.average_ratings.before') !!}

                @if (core()->getConfigData('catalog.products.review.summary') == 'star_counts')
                    <x-shop::products.ratings
                        class="absolute bottom-1.5 items-center !border-white bg-white/80 !px-2 !py-1 text-xs max-sm:!px-1.5 max-sm:!py-0.5 ltr:left-1.5 rtl:right-1.5"
                        ::average="product.ratings.average"
                        ::total="product.ratings.total"
                        ::rating="false"
                        v-if="product.ratings.total"
                    />
                @else
                    <x-shop::products.ratings
                        class="absolute bottom-1.5 items-center !border-white bg-white/80 !px-2 !py-1 text-xs max-sm:!px-1.5 max-sm:!py-0.5 ltr:left-1.5 rtl:right-1.5"
                        ::average="product.ratings.average"
                        ::total="product.reviews.total"
                        ::rating="false"
                        v-if="product.reviews.total"
                    />
                @endif

                {!! view_render_event('qubix.shop.components.products.card.average_ratings.after') !!}

                <div class="action-items bg-black">
                    <!-- Product Sale Badge -->
                    <p
                        class="absolute top-1.5 inline-block rounded-[44px] bg-accent px-2.5 text-sm font-semibold text-white max-sm:px-2 max-sm:py-0.5 max-sm:text-xs ltr:left-1.5 max-sm:ltr:left-0 max-sm:ltr:rounded-l-none max-sm:ltr:rounded-r-xl rtl:right-5 max-sm:rtl:right-0 max-sm:rtl:rounded-r-none max-sm:rtl:rounded-l-xl"
                        v-if="product.on_sale"
                    >
                        @lang('shop::app.components.products.card.sale')
                    </p>

                    <!-- Product New Badge -->
                    <p
                        class="absolute top-1.5 inline-block rounded-[44px] bg-navyBlue px-2.5 text-sm text-white max-sm:px-2 max-sm:py-0.5 max-sm:text-xs ltr:left-1.5 max-sm:ltr:left-0 max-sm:ltr:rounded-l-none max-sm:ltr:rounded-r-xl rtl:right-1.5 max-sm:rtl:right-0 max-sm:rtl:rounded-r-none max-sm:rtl:rounded-l-xl"
                        v-else-if="product.is_new"
                    >
                        @lang('shop::app.components.products.card.new')
                    </p>

                    {{-- Below md the wishlist/compare buttons sit on the image (the card's action row is hidden there); 36px so a thumb can hit them. --}}
                    <div class="opacity-0 transition-opacity duration-300 group-hover:opacity-100 max-lg:opacity-100">

                        {!! view_render_event('qubix.shop.components.products.card.wishlist_option.before') !!}

                        @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                            <span
                                class="absolute top-1.5 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-zinc-200 bg-white text-xl md:hidden ltr:right-1.5 rtl:left-1.5"
                                role="button"
                                aria-label="@lang('shop::app.components.products.card.add-to-wishlist')"
                                tabindex="0"
                                :class="product.is_wishlist ? 'icon-heart-fill text-red-500' : 'icon-heart'"
                                @click="addToWishlist()"
                            >
                            </span>
                        @endif

                        {!! view_render_event('qubix.shop.components.products.card.wishlist_option.after') !!}

                        {!! view_render_event('qubix.shop.components.products.card.compare_option.before') !!}

                        @if (core()->getConfigData('catalog.products.settings.compare_option'))
                            <span
                                class="icon-compare absolute top-12 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-zinc-200 bg-white text-xl md:hidden ltr:right-1.5 rtl:left-1.5"
                                role="button"
                                aria-label="@lang('shop::app.components.products.card.add-to-compare')"
                                tabindex="0"
                                @click="addToCompare(product.id)"
                            >
                            </span>
                        @endif

                        {!! view_render_event('qubix.shop.components.products.card.compare_option.after') !!}

                    </div>
                </div>
            </div>

            <!-- Product Information Section -->
            <div class="-mt-9 grid max-w-[291px] translate-y-9 content-start gap-2.5 bg-white p-2.5 text-center transition-[transform,box-shadow,border-radius] duration-300 ease-[cubic-bezier(0.23,1,0.32,1)] group-hover:-translate-y-0 group-hover:rounded-t-xl group-hover:shadow-[0_-6px_20px_rgba(0,0,0,0.07)] max-md:relative max-md:mt-0 max-md:translate-y-0 max-md:gap-0 max-md:px-0 max-md:py-1.5 max-sm:min-w-[170px] max-sm:max-w-[192px]">

                {!! view_render_event('qubix.shop.components.products.card.name.before') !!}

                {{-- Wrap between words (break-all split names like "Canva/s"), clamp to two lines and reserve both so prices line up across a row.
                     whitespace-normal overrides the product carousel's max-md:whitespace-nowrap. --}}
                <p
                    class="line-clamp-2 min-h-12 whitespace-normal break-words text-[15px] font-medium leading-6 max-md:mb-1.5 max-md:max-w-56 max-sm:min-h-10 max-sm:max-w-[192px] max-sm:text-sm max-sm:leading-5"
                    :title="product.name"
                >
                    @{{ product.name }}
                </p>

                {!! view_render_event('qubix.shop.components.products.card.name.after') !!}

                <!-- Pricing -->
                {!! view_render_event('qubix.shop.components.products.card.price.before') !!}

                <div
                    class="flex items-center justify-center gap-2.5 text-base font-bold text-brand max-sm:text-sm max-sm:leading-6"
                    v-html="product.price_html"
                >
                </div>

                {!! view_render_event('qubix.shop.components.products.card.price.after') !!}

                <!-- Phone: a visible Add to cart (the hover action row below is desktop-only). -->
                @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                    <button
                        type="button"
                        class="mt-2 hidden h-10 w-full items-center justify-center gap-1.5 rounded-full border border-navyBlue text-sm font-semibold text-navyBlue transition-[background-color,color,transform] duration-150 active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50 max-md:flex"
                        :class="{ 'cart-add-btn--loading': isAddingToCart }"
                        :disabled="! product.is_saleable || isAddingToCart"
                        @click="addToCart()"
                    >
                        <template v-if="isAddingToCart">
                            <span class="cart-dots" aria-hidden="true">
                                <span class="cart-dot"></span>
                                <span class="cart-dot"></span>
                                <span class="cart-dot"></span>
                            </span>
                        </template>

                        <template v-else>
                            @lang('shop::app.components.products.card.add-to-cart')
                        </template>
                    </button>
                @endif

                <!-- Product Actions Section -->
                <div class="action-items flex items-center justify-between translate-y-2 opacity-0 transition-[transform,opacity] duration-300 ease-[cubic-bezier(0.23,1,0.32,1)] group-hover:translate-y-0 group-hover:opacity-100 max-md:hidden">
                    @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
                        {!! view_render_event('qubix.shop.components.products.card.add_to_cart.before') !!}

                        <button
                            class="cart-add-btn secondary-button flex w-full max-w-full items-center justify-center gap-1.5 overflow-hidden p-2.5 text-sm font-medium max-sm:rounded-xl max-sm:p-2"
                            :class="{ 'cart-add-btn--loading': isAddingToCart }"
                            :disabled="! product.is_saleable || isAddingToCart"
                            @click="addToCart()"
                        >
                            <!-- Loading state -->
                            <template v-if="isAddingToCart">
                                <span class="cart-adding-text">Adding</span>
                                <span class="cart-dots" aria-hidden="true">
                                    <span class="cart-dot"></span>
                                    <span class="cart-dot"></span>
                                    <span class="cart-dot"></span>
                                </span>
                            </template>

                            <!-- Default state -->
                            <template v-else>
                                <span class="icon-cart text-base leading-none"></span>
                                <span>@lang('shop::app.components.products.card.add-to-cart')</span>
                            </template>
                        </button>

                        {!! view_render_event('qubix.shop.components.products.card.add_to_cart.after') !!}
                    @endif

                    {!! view_render_event('qubix.shop.components.products.card.wishlist_option.before') !!}

                    @if (core()->getConfigData('customer.settings.wishlist.wishlist_option'))
                        <span
                            class="cursor-pointer p-2.5 text-2xl transition-transform duration-200 hover:scale-110 active:scale-95 max-sm:hidden"
                            role="button"
                            aria-label="@lang('shop::app.components.products.card.add-to-wishlist')"
                            tabindex="0"
                            :class="product.is_wishlist ? 'icon-heart-fill text-red-600' : 'icon-heart'"
                            @click="addToWishlist()"
                        >
                        </span>
                    @endif

                    {!! view_render_event('qubix.shop.components.products.card.wishlist_option.after') !!}

                    {!! view_render_event('qubix.shop.components.products.card.compare_option.before') !!}

                    @if (core()->getConfigData('catalog.products.settings.compare_option'))
                        <span
                            class="icon-compare cursor-pointer p-2.5 text-2xl transition-transform duration-200 hover:scale-110 active:scale-95 max-sm:hidden"
                            role="button"
                            aria-label="@lang('shop::app.components.products.card.add-to-compare')"
                            tabindex="0"
                            @click="addToCompare(product.id)"
                        >
                        </span>
                    @endif

                    {!! view_render_event('qubix.shop.components.products.card.compare_option.after') !!}
                </div>
            </div>
        </div>
