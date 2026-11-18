import { test, expect } from "../../setup";
import {
    expectCompareLimitMessage,
    findSaleableProducts,
    openSearchFor,
    setCustomerCompare,
    setGuestCompare,
    signInSeededCustomer,
    type CardProduct,
} from "../../utils/card-styles";

/**
 * Compare holds 4 products everywhere (owner decision, 2026-11-16). This is the Classic card's one
 * intended behaviour change, so it is tested on the Classic card and the product page. The new cards
 * meet the same limit in tray.spec.
 */

test.describe.configure({ mode: "serial" });

/**
 * The Classic card's "Add To Compare". The Classic card has no landmark role, so it is found as the
 * nearest card container (`div.group`) around the product's name.
 */
async function compareOnClassicCard(page, product: CardProduct) {
    const card = page.getByText(product.name, { exact: true }).first().locator("xpath=ancestor::div[contains(@class, 'group')][1]");

    await card.hover();
    await card.getByRole("button", { name: "Add To Compare" }).click();
}

/**
 * The product page's "Compare". Its accessible name starts with the icon font's glyph, so it is matched
 * as "Compare" after any non-letters; that also keeps out the "Add To Compare" buttons of related cards.
 */
async function compareOnProductPage(page, product: CardProduct) {
    await page.goto(product.urlKey);
    await page.getByRole("button", { name: /^[^A-Za-z]*Compare$/ }).click();
}

test("guest, Classic card: a fifth product is refused with a link to the compare page", async ({ page }) => {
    const products = await findSaleableProducts(page.request, 5);

    await openSearchFor(page, products[4]);
    await setGuestCompare(page, products.slice(0, 4).map((product) => product.id));
    await page.reload();

    await compareOnClassicCard(page, products[4]);

    await expectCompareLimitMessage(page);
    expect(await page.evaluate(() => JSON.parse(localStorage.getItem("compare_items") ?? "[]").length)).toBe(4);

    await page.getByRole("link", { name: "Open compare" }).first().click();

    await expect(page).toHaveURL(/\/compare/);
});

test("guest, Classic card: below the limit the add is as before", async ({ page }) => {
    const products = await findSaleableProducts(page.request, 4);

    await openSearchFor(page, products[3]);
    await setGuestCompare(page, products.slice(0, 3).map((product) => product.id));
    await page.reload();

    await compareOnClassicCard(page, products[3]);

    await expect(page.getByText("Item added successfully to compare list.").first()).toBeVisible();
    expect(await page.evaluate(() => JSON.parse(localStorage.getItem("compare_items") ?? "[]").length)).toBe(4);
});

test("guest, product page: a fifth product is refused", async ({ page }) => {
    const products = await findSaleableProducts(page.request, 5);

    await page.goto("");
    await setGuestCompare(page, products.slice(0, 4).map((product) => product.id));

    await compareOnProductPage(page, products[4]);

    await expectCompareLimitMessage(page);
});

test("signed-in customer, Classic card and product page: a fifth product is refused by the server", async ({ page }) => {
    const products = await findSaleableProducts(page.request, 5);

    // The suite's marked e2e customer (seeded by the global setup, removed by the teardown).
    await signInSeededCustomer(page);
    await setCustomerCompare(page, products.slice(0, 4).map((product) => product.id));

    await openSearchFor(page, products[4]);
    await compareOnClassicCard(page, products[4]);

    await expectCompareLimitMessage(page);

    await compareOnProductPage(page, products[4]);

    await expectCompareLimitMessage(page);

    const { data } = await (await page.request.get("api/compare-items")).json();

    expect(data).toHaveLength(4);

    await setCustomerCompare(page, []);
});
