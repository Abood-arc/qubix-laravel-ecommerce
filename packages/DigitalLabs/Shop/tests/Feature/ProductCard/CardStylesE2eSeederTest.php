<?php

use DigitalLabs\Customer\Models\Customer;
use DigitalLabs\Shop\Tests\Seeders\CardStylesE2eCleanupSeeder;
use DigitalLabs\Shop\Tests\Seeders\CardStylesE2eSeeder;
use Webkul\Faker\Helpers\Customer as CustomerFaker;

/*
 * The card-styles e2e customer: one marked row, recreated on every run, removed by the cleanup,
 * and nothing else ever touched.
 */

it('creates exactly one marked customer, however often it runs', function () {
    (new CardStylesE2eSeeder)->run();
    (new CardStylesE2eSeeder)->run();

    expect(Customer::where('email', CardStylesE2eSeeder::CUSTOMER_EMAIL)->count())->toBe(1);
});

it('removes only the marked customer', function () {
    $lookalikes = collect(['x-e2e-cards-customer@example.test', 'e2e-cards-customer@example.testing', 'e2e-buyer@example.test'])
        ->map(fn ($email) => (new CustomerFaker)->factory()->create(['email' => $email]));

    (new CardStylesE2eSeeder)->run();
    (new CardStylesE2eCleanupSeeder)->run();

    expect(Customer::where('email', CardStylesE2eSeeder::CUSTOMER_EMAIL)->exists())->toBeFalse()
        ->and(Customer::whereIn('id', $lookalikes->pluck('id'))->count())->toBe(3);
});

it('refuses to run outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => (new CardStylesE2eSeeder)->run())->toThrow(RuntimeException::class)
            ->and(fn () => (new CardStylesE2eCleanupSeeder)->run())->toThrow(RuntimeException::class);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});
