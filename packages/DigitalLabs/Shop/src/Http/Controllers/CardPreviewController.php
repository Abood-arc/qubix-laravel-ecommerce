<?php

namespace DigitalLabs\Shop\Http\Controllers;

use DigitalLabs\Product\Repositories\ProductRepository;
use DigitalLabs\Shop\Enums\ProductCardStyleEnum;
use DigitalLabs\Shop\Enums\StoreLookEnum;
use DigitalLabs\Shop\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The live previews of Admin > Configuration > General > Design (Product card and Store look): the real
 * product card, in a given style and look, with the store's own products, playing a short demo on a
 * loop (card/demo). For signed-in admins only. The style and look are for this response alone: nothing
 * is saved, and shoppers never see them. Not cached (no cache.response), not indexed, not linked from
 * the storefront, and only ever shown inside the admin (same-origin framing).
 */
class CardPreviewController extends Controller
{
    /**
     * Products the preview shows: two cards side by side, as on a phone.
     */
    public const PRODUCTS = 2;

    public function __construct(protected ProductRepository $productRepository) {}

    public function show(Request $request): Response
    {
        abort_unless(auth()->guard('admin')->check(), 403);

        $style = ProductCardStyleEnum::tryFrom((string) $request->query('style'));

        $look = StoreLookEnum::tryFrom((string) $request->query('look'));

        abort_unless($style && $look, 404);

        // Read by the card component (ProductCardStyleEnum::forPage) for this response only.
        $request->attributes->set(ProductCardStyleEnum::PREVIEW_ATTRIBUTE, $style);

        return response()
            ->view('shop::card-preview.index', [
                'look' => $look,
                'withHeader' => $request->boolean('header'),
                'products' => $this->products(),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Content-Security-Policy', "frame-ancestors 'self'");
    }

    /**
     * The store's own products, as the listing API sends them to the cards: saleable ones with more
     * than one photo first, so the demo has photos to swipe and something to add to the cart.
     */
    protected function products(): array
    {
        $products = $this->productRepository
            ->setSearchEngine('database')
            ->getAll([
                'channel_id' => core()->getCurrentChannel()->id,
                'status' => 1,
                'visible_individually' => 1,
                'limit' => 24,
            ]);

        return collect(ProductResource::collection($products->getCollection())->resolve())
            ->sortByDesc(fn (array $product) => (int) $product['is_saleable'] * 2 + (int) (count($product['images']) > 1))
            ->take(self::PRODUCTS)
            ->values()
            ->all();
    }
}
