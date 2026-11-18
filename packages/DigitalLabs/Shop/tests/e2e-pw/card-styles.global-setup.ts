import { execSync } from "node:child_process";

/**
 * Seeds the marked customer the signed-in compare specs sign in as. The seeder removes an earlier copy
 * first and refuses to run outside APP_ENV local/testing.
 */
export default async function globalSetup() {
    const artisan = process.env.E2E_ARTISAN ?? "php artisan";

    execSync(`${artisan} db:seed --class="DigitalLabs\\\\Shop\\\\Tests\\\\Seeders\\\\CardStylesE2eSeeder" --force`, {
        stdio: "inherit",
    });
}
