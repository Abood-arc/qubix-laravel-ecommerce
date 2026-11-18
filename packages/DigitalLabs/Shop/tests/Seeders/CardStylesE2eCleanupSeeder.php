<?php

namespace DigitalLabs\Shop\Tests\Seeders;

/**
 * Removes what CardStylesE2eSeeder created, and nothing else. Run by the card-styles Playwright
 * global teardown unless E2E_KEEP=1.
 */
class CardStylesE2eCleanupSeeder extends CardStylesE2eSeeder
{
    public function run(): void
    {
        $this->guard();

        $this->clean();
    }
}
