<?php

namespace DigitalLabs\Admin\Tests\Concerns;

use DigitalLabs\User\Contracts\Admin as AdminContract;
use DigitalLabs\User\Models\Admin as AdminModel;

trait AdminTestBench
{
    /**
     * Login as customer.
     */
    public function loginAsAdmin(?AdminContract $admin = null): AdminContract
    {
        $admin = $admin ?? AdminModel::factory()->create();

        $this->actingAs($admin, 'admin');

        return $admin;
    }
}
