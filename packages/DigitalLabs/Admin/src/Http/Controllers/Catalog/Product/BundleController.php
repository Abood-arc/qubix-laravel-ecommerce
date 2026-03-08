<?php

namespace DigitalLabs\Admin\Http\Controllers\Catalog\Product;

use Illuminate\Http\JsonResponse;
use DigitalLabs\Admin\Http\Controllers\Controller;
use DigitalLabs\Product\Helpers\BundleOption;
use DigitalLabs\Product\Repositories\ProductRepository;

class BundleController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ProductRepository $productRepository,
        protected BundleOption $bundleOptionHelper
    ) {}

    /**
     * Returns the compare items of the customer.
     */
    public function options(int $id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);

        return new JsonResponse([
            'data' => $this->bundleOptionHelper->getBundleConfig($product),
        ]);
    }
}
