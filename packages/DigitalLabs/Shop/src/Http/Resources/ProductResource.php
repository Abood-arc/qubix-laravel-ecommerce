<?php

namespace DigitalLabs\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use DigitalLabs\Product\Helpers\Review;

class ProductResource extends JsonResource
{
    /**
     * Product cards say "Only N left" from this many units down.
     */
    public const LOW_STOCK_THRESHOLD = 5;

    /**
     * Create a new resource instance.
     *
     * @param  mixed  $resource
     * @return void
     */
    public function __construct($resource)
    {
        $this->reviewHelper = app(Review::class);

        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $productTypeInstance = $this->getTypeInstance();

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'url_key' => $this->url_key,
            'base_image' => product_image()->getProductBaseImage($this),
            'images' => product_image()->getGalleryImages($this),
            'is_new' => (bool) $this->new,
            'is_featured' => (bool) $this->featured,
            'on_sale' => (bool) $productTypeInstance->haveDiscount(),
            'is_saleable' => (bool) $productTypeInstance->isSaleable(),
            'is_wishlist' => (bool) auth()->guard()->user()?->wishlist_items
                ->where('channel_id', core()->getCurrentChannel()->id)
                ->where('product_id', $this->id)->count(),
            'min_price' => core()->formatPrice($productTypeInstance->getMinimalPrice()),
            'prices' => $productTypeInstance->getProductPrices(),
            'price_html' => $productTypeInstance->getPriceHtml(),
            'ratings' => [
                'average' => $this->reviewHelper->getAverageRating($this),
                'total' => $this->reviewHelper->getTotalRating($this),
            ],
            'reviews' => [
                'total' => $this->reviewHelper->getTotalReviews($this),
            ],
            'dimensions' => $this->dimensions(),
            'stock_left' => $this->lowStockLeft($productTypeInstance),
        ];
    }

    /**
     * Width, height and depth for the product card's size line, from the product's `width`,
     * `height` and `length` attributes (`length` is the depth or gusset). Null unless width
     * and height are both set.
     */
    protected function dimensions(): ?array
    {
        $width = $this->dimension($this->width);

        $height = $this->dimension($this->height);

        if (! $width || ! $height) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'depth' => $this->dimension($this->length),
        ];
    }

    /**
     * A positive number as typed in the admin, without trailing zeros ("16.50" becomes "16.5").
     */
    protected function dimension(mixed $value): ?string
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * Units left when stock is low (1 to LOW_STOCK_THRESHOLD), otherwise null.
     *
     * The source is the product's inventory index for the current channel
     * (`product_inventory_indices.qty`: stock in the channel's active inventory sources minus
     * quantities on open orders). Only for stock-managed products that hold their own stock:
     * configurable, bundle and grouped products add up several items, and virtual,
     * downloadable and booking products have no stock.
     */
    protected function lowStockLeft($productTypeInstance): ?int
    {
        if (
            ! $productTypeInstance->isStockable()
            || $productTypeInstance->isComposite()
            || ! $this->manage_stock
        ) {
            return null;
        }

        $quantity = (int) $productTypeInstance->totalQuantity();

        return $quantity >= 1 && $quantity <= self::LOW_STOCK_THRESHOLD ? $quantity : null;
    }
}
