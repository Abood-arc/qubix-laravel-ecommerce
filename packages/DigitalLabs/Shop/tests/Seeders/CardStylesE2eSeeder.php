<?php

namespace DigitalLabs\Shop\Tests\Seeders;

use DigitalLabs\Customer\Models\Customer;
use DigitalLabs\Customer\Models\CustomerGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Test data for the product card styles Playwright suite (Shop/tests/e2e-pw/tests/card-styles):
 * one throwaway customer for the signed-in compare tests.
 *
 *   php artisan db:seed --class="DigitalLabs\\Shop\\Tests\\Seeders\\CardStylesE2eSeeder" --force
 *
 * Idempotent: every run first removes the customer it owns, matched by its exact marker email, then
 * creates it again. It never touches any other customer, so it is safe on a copy of the real store.
 * It creates a customer with a documented default password, so it refuses to run outside local/testing.
 * Same pattern as the admin suite's E2eSeeder.
 */
class CardStylesE2eSeeder extends Seeder
{
    public const CUSTOMER_EMAIL = 'e2e-cards-customer@example.test';

    public function run(): void
    {
        $this->guard();

        $this->clean();

        $this->createCustomer();
    }

    /**
     * Never create a known-password customer on a real site.
     */
    protected function guard(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Card styles E2E seeder refuses to run outside local/testing (APP_ENV is "'.app()->environment().'").');
        }
    }

    /**
     * Remove the customer an earlier run created; its carts and compare rows go with it (the foreign
     * keys cascade). Exact email match: nothing else goes.
     */
    public function clean(): void
    {
        Customer::where('email', self::CUSTOMER_EMAIL)->get()->each->delete();
    }

    protected function createCustomer(): void
    {
        Customer::create([
            'first_name' => 'E2E',
            'last_name' => 'Cards Customer',
            'email' => self::CUSTOMER_EMAIL,
            'password' => Hash::make(env('E2E_CUSTOMER_PASSWORD', 'e2e-customer-password')),
            'customer_group_id' => CustomerGroup::where('code', 'general')->value('id'),
            'channel_id' => core()->getDefaultChannel()->id,
            'status' => 1,
            'is_verified' => 1,
            'is_suspended' => 0,
        ]);
    }
}
