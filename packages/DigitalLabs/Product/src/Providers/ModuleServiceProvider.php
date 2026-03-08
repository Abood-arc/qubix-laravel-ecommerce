<?php

namespace DigitalLabs\Product\Providers;

use DigitalLabs\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        \DigitalLabs\Product\Models\Product::class,
        \DigitalLabs\Product\Models\ProductAttributeValue::class,
        \DigitalLabs\Product\Models\ProductBundleOption::class,
        \DigitalLabs\Product\Models\ProductBundleOptionProduct::class,
        \DigitalLabs\Product\Models\ProductBundleOptionTranslation::class,
        \DigitalLabs\Product\Models\ProductCustomerGroupPrice::class,
        \DigitalLabs\Product\Models\ProductCustomizableOption::class,
        \DigitalLabs\Product\Models\ProductCustomizableOptionPrice::class,
        \DigitalLabs\Product\Models\ProductCustomizableOptionTranslation::class,
        \DigitalLabs\Product\Models\ProductDownloadableLink::class,
        \DigitalLabs\Product\Models\ProductDownloadableSample::class,
        \DigitalLabs\Product\Models\ProductFlat::class,
        \DigitalLabs\Product\Models\ProductGroupedProduct::class,
        \DigitalLabs\Product\Models\ProductImage::class,
        \DigitalLabs\Product\Models\ProductInventory::class,
        \DigitalLabs\Product\Models\ProductInventoryIndex::class,
        \DigitalLabs\Product\Models\ProductOrderedInventory::class,
        \DigitalLabs\Product\Models\ProductPriceIndex::class,
        \DigitalLabs\Product\Models\ProductReview::class,
        \DigitalLabs\Product\Models\ProductReviewAttachment::class,
        \DigitalLabs\Product\Models\ProductSalableInventory::class,
        \DigitalLabs\Product\Models\ProductVideo::class,
    ];
}
