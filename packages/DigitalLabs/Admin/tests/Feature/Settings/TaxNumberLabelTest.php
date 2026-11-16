<?php

use function Pest\Laravel\get;

it('names the store tax number for both GSTIN and VAT on the shipping origin settings', function () {
    $this->loginAsAdmin();

    get(route('admin.configuration.index', ['sales', 'shipping']))
        ->assertOk()
        // Label and hint reach the page as the field's attributes, which Vue displays.
        ->assertSee(trans('admin::app.configuration.index.sales.shipping-setting.origin.vat-number'))
        ->assertSee('info="'.e(trans('admin::app.configuration.index.sales.shipping-setting.origin.vat-number-info')).'"', false);

    expect(trans('admin::app.configuration.index.sales.shipping-setting.origin.vat-number'))->toBe('GSTIN / VAT Number');
});
