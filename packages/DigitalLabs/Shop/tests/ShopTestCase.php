<?php

namespace DigitalLabs\Shop\Tests;

use Tests\TestCase;
use DigitalLabs\Core\Tests\Concerns\CoreAssertions;
use DigitalLabs\Shop\Tests\Concerns\ProductCardTestBench;
use DigitalLabs\Shop\Tests\Concerns\RazorpayTestBench;
use DigitalLabs\Shop\Tests\Concerns\ShopTestBench;

class ShopTestCase extends TestCase
{
    use CoreAssertions, ProductCardTestBench, RazorpayTestBench, ShopTestBench;
}
