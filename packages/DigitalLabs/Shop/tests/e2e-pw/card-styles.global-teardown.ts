import { execSync } from "node:child_process";

/**
 * Removes the marked customer again, so the dev database is left as it was found. E2E_KEEP=1 keeps it.
 */
export default async function globalTeardown() {
    if (process.env.E2E_KEEP === "1") {
        return;
    }

    const artisan = process.env.E2E_ARTISAN ?? "php artisan";

    execSync(`${artisan} db:seed --class="DigitalLabs\\\\Shop\\\\Tests\\\\Seeders\\\\CardStylesE2eCleanupSeeder" --force`, {
        stdio: "inherit",
    });
}
