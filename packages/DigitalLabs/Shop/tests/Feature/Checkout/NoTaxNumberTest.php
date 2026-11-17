<?php

use Webkul\Faker\Helpers\Customer as CustomerFaker;

use function Pest\Laravel\get;

/*
 * The storefront asks for no tax number (GSTIN / VAT ID) on either store; company name
 * stays an ordinary optional field. Admin keeps the field for staff-entered addresses.
 */

beforeEach(function () {
    config(['responsecache.enabled' => false]);
});

it('asks for company name but no tax number in the checkout address form', function () {
    $customer = (new CustomerFaker)->factory()->create();

    $this->loginAsCustomer($customer);

    $this->createRazorpayCart($customer);

    get(route('shop.checkout.onepage.index'))
        ->assertOk()
        ->assertSee("controlName + '.company_name'", false)
        ->assertDontSee("controlName + '.vat_id'", false)
        ->assertDontSee('showBusinessFields', false);
});
