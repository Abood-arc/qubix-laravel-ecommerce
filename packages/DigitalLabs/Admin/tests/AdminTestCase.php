<?php

namespace DigitalLabs\Admin\Tests;

use Tests\TestCase;
use DigitalLabs\Admin\Tests\Concerns\AdminTestBench;
use DigitalLabs\Core\Tests\Concerns\CoreAssertions;

class AdminTestCase extends TestCase
{
    use AdminTestBench, CoreAssertions;
}
