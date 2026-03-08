<?php

namespace DigitalLabs\Shop\Tests\Concerns;

use DigitalLabs\Customer\Contracts\Customer as CustomerContract;
use DigitalLabs\Faker\Helpers\Customer as CustomerFaker;

trait ShopTestBench
{
    /**
     * Login as customer.
     */
    public function loginAsCustomer(?CustomerContract $customer = null): CustomerContract
    {
        $customer = $customer ?? (new CustomerFaker)->factory()->create();

        $this->actingAs($customer);

        return $customer;
    }
}
