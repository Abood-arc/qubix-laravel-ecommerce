import { defineConfig } from "@playwright/test";
import base from "./playwright.config";

/**
 * The product card styles suite (tests/card-styles): the Shop suite's settings, plus a marked throwaway
 * customer (CardStylesE2eSeeder) that the global setup seeds and the global teardown removes. Run it from
 * packages/DigitalLabs/Shop. Locally the seeder runs in the container, always as sail:
 *
 *   E2E_ARTISAN="docker exec -u sail -w /var/www/html qubix-cards-app php artisan" \
 *     npx playwright test --config=tests/e2e-pw/card-styles.config.ts
 *
 * E2E_KEEP=1 keeps the customer after the run (to debug a failure).
 */
export default defineConfig({
    ...base,
    testDir: "./tests/card-styles",
    globalSetup: "./card-styles.global-setup.ts",
    globalTeardown: "./card-styles.global-teardown.ts",
});
