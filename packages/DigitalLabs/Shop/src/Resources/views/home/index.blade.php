@php
    $channel = core()->getCurrentChannel();
    $isHeroCarouselRendered = false;
    $homeLayoutMode = config('shop.home.layout_mode', 'legacy');
@endphp

<!-- SEO Meta Content -->
@push ('meta')
    <meta
        name="title"
        content="{{ $channel->home_seo['meta_title'] ?? '' }}"
    />

    <meta
        name="description"
        content="{{ $channel->home_seo['meta_description'] ?? '' }}"
    />

    <meta
        name="keywords"
        content="{{ $channel->home_seo['meta_keywords'] ?? '' }}"
    />

    @if ($homeLayoutMode === 'v2')
        {{-- Visible in DevTools / View Source; not shown on screen --}}
        <meta name="velocity-home-layout" content="v2">
    @endif
@endPush

@push('scripts')
    @if(! empty($categories))
        <script>
            localStorage.setItem('categories', JSON.stringify(@json($categories)));
        </script>
    @endif
@endpush

<x-shop::layouts>
    <!-- Page Title -->
    <x-slot:title>
        {{  $channel->home_seo['meta_title'] ?? '' }}
    </x-slot>

    <div class="home-premium bg-[#faf9f7] pb-10">
        <!-- Loop over the theme customization -->
        @foreach ($customizations as $customization)
            @php ($data = $customization->options) @endphp

            {{--
                Skip legacy CMS blocks that only rendered fake “product” grids in HTML.
                Real catalog products on the home page come from PRODUCT_CAROUSEL (API).
            --}}
            @if (
                $homeLayoutMode !== 'v2'
                && $customization->type === 'static_content'
                && ! empty($data['html'] ?? '')
                && (
                    str_contains($data['html'], 'top-collection-container')
                    || str_contains($data['html'], 'section-game')
                    || str_contains($data['html'], 'Our Collections')
                )
            )
                @continue
            @endif

            <!-- Static content -->
            @switch ($customization->type)
                @case ($customization::IMAGE_CAROUSEL)
                    @if (! $isHeroCarouselRendered)
                        <section class="home-hero-section pt-0">
                            <div class="w-full overflow-hidden bg-white shadow-[0_24px_50px_rgba(15,23,42,0.1)]">
                                <x-shop::carousel
                                    :options="$data"
                                    aria-label="{{ trans('shop::app.home.index.image-carousel') }}"
                                />
                            </div>
                        </section>
                    @else
                        <section class="mt-8">
                            <x-shop::carousel
                                :options="$data"
                                aria-label="{{ trans('shop::app.home.index.image-carousel') }}"
                            />
                        </section>
                    @endif

                    @php
                        $isHeroCarouselRendered = true;
                    @endphp
                    @break
                @case ($customization::STATIC_CONTENT)
                    @if ($homeLayoutMode !== 'v2')
                        <!-- push style -->
                        @if (! empty($data['css']))
                            @push ('styles')
                                <style>
                                    {{ $data['css'] }}
                                </style>
                            @endpush
                        @endif

                        <!-- render html -->
                        @if (! empty($data['html']))
                            <section class="mx-auto mt-10 max-w-[1440px] px-3 md:px-6">
                                {!! $data['html'] !!}
                            </section>
                        @endif
                    @endif

                    @break
                @case ($customization::CATEGORY_CAROUSEL)
                    @if ($homeLayoutMode !== 'v2')
                        <!-- Categories carousel -->
                        <section class="mx-auto mt-12 max-w-[1440px] px-3 md:px-6">
                            <x-shop::categories.carousel
                                :title="$data['title'] ?? ''"
                                :src="route('shop.api.categories.index', $data['filters'] ?? [])"
                                :navigation-link="route('shop.home.index')"
                                aria-label="{{ trans('shop::app.home.index.categories-carousel') }}"
                            />
                        </section>
                    @endif

                    @break
                @case ($customization::PRODUCT_CAROUSEL)
                    @if ($homeLayoutMode !== 'v2')
                        <!-- Product Carousel -->
                        <section class="mx-auto mt-12 max-w-[1440px] px-3 md:px-6">
                            <x-shop::products.carousel
                                :title="$data['title'] ?? ''"
                                :src="route('shop.api.products.index', $data['filters'] ?? [])"
                                :navigation-link="route('shop.search.index', $data['filters'] ?? [])"
                                aria-label="{{ trans('shop::app.home.index.product-carousel') }}"
                            />
                        </section>
                    @endif

                    @break
            @endswitch
        @endforeach

        @if ($homeLayoutMode === 'v2')
            @include('shop::home.v2-placeholder')
        @endif
    </div>
</x-shop::layouts>
