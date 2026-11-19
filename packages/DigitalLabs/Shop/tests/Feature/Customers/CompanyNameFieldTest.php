<?php

use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Customer\Models\CustomerAddress;

use function Pest\Laravel\get;

/**
 * Customer > Address > Information: "Show Company Name" (on unless the admin switches it off).
 */
function companyNamePages(CustomerAddress $address): array
{
    return [
        route('shop.checkout.onepage.index') => 'shop::app.checkout.onepage.address.company-name',
        route('shop.customers.account.addresses.create') => 'shop::app.customers.account.addresses.create.company-name',
        route('shop.customers.account.addresses.edit', $address->id) => 'shop::app.customers.account.addresses.edit.company-name',
    ];
}

beforeEach(function () {
    CoreConfig::query()->where('code', 'customer.address.information.company_name')->get()->each->delete();

    $customer = $this->loginAsCustomer();

    $this->createRazorpayCart($customer);

    $this->address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
});

it('shows the company name field by default', function () {
    foreach (companyNamePages($this->address) as $page => $label) {
        get($page)->assertOk()->assertSee(trans($label));
    }
});

it('hides the company name field when the admin switches it off', function () {
    CoreConfig::create([
        'code' => 'customer.address.information.company_name',
        'value' => '0',
        'channel_code' => core()->getRequestedChannelCode(),
    ]);

    foreach (companyNamePages($this->address) as $page => $label) {
        get($page)->assertOk()->assertDontSee(trans($label));
    }
});
