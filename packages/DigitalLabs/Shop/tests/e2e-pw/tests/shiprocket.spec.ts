/**
 * Shiprocket at checkout and on the admin order page, against the FAKE Shiprocket client.
 *
 * Needs (never point this at a live or shared database):
 * - APP_CURRENCY=INR, SHIPROCKET_FAKE=true, SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD set (any value),
 *   QUEUE_CONNECTION=sync (so "Ship with Shiprocket" books inside the request), DEBUGBAR off.
 * - Full-page cache off AND on a different store than the app cache (e.g. CACHE_STORE=file,
 *   RESPONSE_CACHE_ENABLED=false, RESPONSE_CACHE_DRIVER=array): placing an order clears the
 *   response cache, which on a shared store also wipes the fake client's AWB counter, and the
 *   next booking then reissues an AWB already on file (unique awb_code -> HTTP 500).
 * - Serve with `php -S` (or nginx/FPM), not `artisan serve`: `serve` drops env overrides for
 *   keys that are in .env, so DB_DATABASE etc. silently fall back to .env.
 * - The seed in utils/shiprocket-seed.php: INR store, weight unit g, Shiprocket on with its PIN check
 *   (pickup PIN 110001, no automatic booking), Cash on Delivery + Money Transfer + Free Shipping
 *   active, and the 500 g product `shiprocket-e2e-bag`.
 *
 *   php artisan tinker --execute="require 'packages/DigitalLabs/Shop/tests/e2e-pw/utils/shiprocket-seed.php';"
 *
 * The fake client: a delivery PIN starting with 9 is not deliverable, one ending in 0 has no
 * Cash on Delivery, any other PIN has both, with two couriers: Fake Surface (₹90, 5 days) and
 * Fake Air (₹150, 2 days), each ₹50 more with Cash on Delivery.
 *
 * Run: APP_URL=http://localhost:8282 npx playwright test tests/shiprocket.spec.ts
 */
import { type Page } from "@playwright/test";
import { test, expect } from "../setup";

const PRODUCT_URL_KEY = "shiprocket-e2e-bag";

/**
 * Waits after a server round trip (address check, order placement, booking): generous, as the
 * shared dev box can be slow.
 */
const ROUND_TRIP = { timeout: 60_000 };

test.describe.configure({ timeout: 300_000 });

/**
 * Puts the seeded product in a fresh guest cart and opens checkout.
 */
async function startGuestCheckout(page: Page) {
    await page.goto(PRODUCT_URL_KEY);
    await page.waitForLoadState("networkidle");

    const added = page.waitForResponse(
        (response) =>
            response.url().endsWith("/api/checkout/cart") && response.request().method() === "POST",
        ROUND_TRIP
    );

    await page.getByRole("button", { name: "Add To Cart" }).first().click();

    expect((await added).ok()).toBe(true);

    await page.goto("checkout/onepage");
}

/**
 * Fills the guest billing address (used for shipping) with an Indian address and proceeds.
 */
async function submitIndianAddress(page: Page, pin: string) {
    await page.locator('input[name="billing.first_name"]').fill("Asha");
    await page.locator('input[name="billing.last_name"]').fill("Guest");
    await page.locator('input[name="billing.email"]').fill(`asha.${pin}@example.test`);
    await page.locator('input[name="billing.address.[0]"]').first().fill("12 Janpath Road");
    await page.locator('select[name="billing.country"]').selectOption("IN");
    await page.locator('select[name="billing.state"]').selectOption("DL");
    await page.locator('input[name="billing.city"]').fill("New Delhi");
    await page.locator('input[name="billing.postcode"]').fill(pin);
    await page.locator('input[name="billing.phone"]').fill("9876543210");

    await page.getByRole("button", { name: "Proceed" }).click();
}

/**
 * Picks Free Shipping and waits for the payment methods.
 */
async function chooseFreeShipping(page: Page) {
    await page.getByText("Free Shipping").first().click({ timeout: ROUND_TRIP.timeout });
    await expect(page.getByAltText("Money Transfer")).toBeVisible(ROUND_TRIP);
}

test.describe("Shiprocket PIN checks at checkout", () => {
    test("refuses a PIN code Shiprocket cannot deliver to", async ({ shopPage }) => {
        await startGuestCheckout(shopPage);
        await submitIndianAddress(shopPage, "900001");

        // The error sits on the postcode field.
        await expect(shopPage.locator('#steps-container p[name="billing.postcode"]')).toHaveText(
            "Sorry, we can't deliver to PIN code 900001 yet.",
            ROUND_TRIP
        );

        // Checkout does not move on to shipping.
        await expect(shopPage.getByText("Free Shipping")).toHaveCount(0);
        await expect(shopPage.getByRole("button", { name: "Proceed" })).toBeVisible();
    });

    test("greys out Cash on Delivery where no courier collects cash", async ({ shopPage }) => {
        await startGuestCheckout(shopPage);
        await submitIndianAddress(shopPage, "110010");
        await chooseFreeShipping(shopPage);

        await expect(
            shopPage.locator("#steps-container").getByText(
                "Cash on Delivery isn't available for PIN code 110010. Please pay online."
            )
        ).toBeVisible();

        await expect(shopPage.locator("input#cashondelivery")).toBeDisabled();
        await expect(
            shopPage.locator('label[for="cashondelivery"][aria-disabled="true"]')
        ).toHaveCount(1);

        // Clicking it does nothing: no order review, no Place Order.
        await shopPage.getByAltText("Cash On Delivery").click({ force: true });
        await expect(shopPage.locator("input#cashondelivery")).not.toBeChecked();
        await expect(shopPage.getByRole("button", { name: "Place Order" })).toHaveCount(0);

        // Another method can be picked.
        await expect(shopPage.locator("input#moneytransfer")).toBeEnabled();
        await shopPage.getByAltText("Money Transfer").click();
        await expect(shopPage.getByRole("button", { name: "Place Order" })).toBeVisible();
    });
});

test.describe("Shiprocket live delivery prices", () => {
    test("charges the courier's price and adds the cash on delivery fee when paying cash", async ({ shopPage }) => {
        await startGuestCheckout(shopPage);
        await submitIndianAddress(shopPage, "110001");

        const steps = shopPage.locator("#steps-container");

        // Standard (cheapest courier) and Express (fastest), at the fake client's prices.
        const standard = steps.locator("label", { hasText: "Standard delivery" });
        const express = steps.locator("label", { hasText: "Express delivery" });

        await expect(standard).toContainText("₹90.00", ROUND_TRIP);
        await expect(standard).toContainText(/Arrives by \d{1,2} \w{3}/);
        await expect(express).toContainText("₹150.00");

        await standard.click();
        await expect(shopPage.getByAltText("Money Transfer")).toBeVisible(ROUND_TRIP);

        // Cash on Delivery says what paying cash adds, before it is chosen.
        await expect(steps.getByText(/A cash on delivery fee of ₹50\.00 is added to delivery\./)).toBeVisible();

        const deliveryCharges = shopPage
            .locator("div.flex.justify-between", { has: shopPage.getByText(/^\s*Delivery Charges/) })
            .first();

        await shopPage.getByAltText("Money Transfer").click();
        await expect(deliveryCharges).toContainText("₹90.00", ROUND_TRIP);

        await shopPage.getByAltText("Cash On Delivery").click();
        await expect(deliveryCharges).toContainText("₹140.00", ROUND_TRIP);

        await shopPage.getByRole("button", { name: "Place Order" }).click();
        await expect(shopPage.getByText("Thank you for your order!")).toBeVisible(ROUND_TRIP);
    });
});

test.describe.serial("Shiprocket COD order, booked from the admin order page", () => {
    let orderNumber: string;

    test("a deliverable PIN with Cash on Delivery places a COD order", async ({ shopPage }) => {
        await startGuestCheckout(shopPage);
        await submitIndianAddress(shopPage, "110001");
        await chooseFreeShipping(shopPage);

        await expect(shopPage.locator("input#cashondelivery")).toBeEnabled();
        await expect(shopPage.locator("#steps-container").getByText(/Cash on Delivery isn't available/)).toHaveCount(0);

        await shopPage.getByAltText("Cash On Delivery").click();
        await shopPage.getByRole("button", { name: "Place Order" }).click();

        await expect(shopPage.getByText("Thank you for your order!")).toBeVisible(ROUND_TRIP);

        const info = await shopPage.getByText(/Your order id is #/).innerText();

        orderNumber = info.match(/#(\S+)/)![1];

        expect(orderNumber).toBeTruthy();
    });

    test("admin ships the COD order with Shiprocket and gets an AWB and label", async ({ adminPage }) => {
        await openAdminOrder(adminPage, orderNumber);

        const panel = adminPage.locator("#shiprocket-panel");

        await expect(panel.getByText("Not sent to Shiprocket yet.")).toBeVisible();

        // A 500 g parcel is inside the weight limits, so booking must not ask for confirmation.
        const dialogs: string[] = [];

        adminPage.on("dialog", (dialog) => {
            dialogs.push(dialog.message());
            dialog.dismiss();
        });

        await panel.getByRole("button", { name: "Ship with Shiprocket" }).click();

        await expect(
            adminPage.locator("#app").getByText("Sent to Shiprocket. This page updates once booking finishes.")
        ).toBeVisible(ROUND_TRIP);

        expect(dialogs).toEqual([]);

        // Booked with the fake courier; the fake accepts the pickup request straight away.
        const shipped = adminPage.locator("#shiprocket-panel");

        await expect(shipped.getByText("Pickup scheduled")).toBeVisible();
        await expect(shipped.getByText("Fake Express")).toBeVisible();

        const awb = (await shipped.getByText(/FAKE\d+/).innerText()).match(/FAKE\d+/)![0];

        await expect(shipped.getByRole("link", { name: "Track" })).toHaveAttribute(
            "href",
            `https://shiprocket.co/tracking/${awb}`
        );
        await expect(shipped.getByRole("link", { name: "Download label" })).toHaveAttribute(
            "href",
            /\/admin\/sales\/orders\/\d+\/shiprocket\/label$/
        );
        // The label link sends the admin on to the courier label PDF.
        const labelHref = await shipped.getByRole("link", { name: "Download label" }).getAttribute("href");
        const label = await adminPage.request.get(labelHref!, { maxRedirects: 0 });

        expect(label.status()).toBe(302);
        expect(label.headers()["location"]).toMatch(/^https:\/\/example\.test\/labels\/\d+\.pdf$/);

        await expect(shipped.getByRole("button", { name: "Ship with Shiprocket" })).toHaveCount(0);

        // The order now has a store shipment carrying the courier and the AWB.
        await expect(adminPage.locator("#app").getByText("Shipments (1)")).toBeVisible();

        await adminPage.locator('#app a[href*="/admin/sales/shipments/view/"]').first().click();
        await adminPage.waitForURL(/\/admin\/sales\/shipments\/view\/\d+/);

        await expect(adminPage.locator("#app").getByText("Shiprocket – Fake Express")).toBeVisible();
        await expect(adminPage.locator("#app").getByText(awb, { exact: true })).toBeVisible();
    });
});

/**
 * Opens an order's admin page from the orders list by its number.
 */
async function openAdminOrder(adminPage: Page, orderNumber: string) {
    await adminPage.goto("admin/sales/orders");

    const search = adminPage.getByPlaceholder("Search").first();

    await search.fill(orderNumber);
    await search.press("Enter");

    const row = adminPage.locator(".row").filter({ hasText: `#${orderNumber}` }).first();

    await expect(row).toBeVisible(ROUND_TRIP);

    await row.locator(".icon-sort-right, a[href*='/sales/orders/view/']").first().click();

    await adminPage.waitForURL(/\/admin\/sales\/orders\/view\/\d+/);
}
