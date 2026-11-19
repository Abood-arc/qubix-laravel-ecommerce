import { devices, type Page } from "@playwright/test";
import { test, expect } from "../setup";
import { addToCartByApi, blockChatWidget, headerCartCount, holdCartRequests } from "../utils/cart";

/**
 * The cart page saves a quantity change by itself, half a second after the last − or + (owner decision,
 * 2026-11-19): there is no Update Cart button. The totals fade while they are out of date. A refused
 * change goes back to what the cart holds and says why. Every test starts as a new guest with one item.
 */

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

test.use(pixel7);

const REFUSED = "The requested quantity is not available, please try again later.";

async function openCartWithOneItem(page: Page): Promise<void> {
    await blockChatWidget(page);

    await page.goto("");

    const { data } = await (await page.request.get("api/products?limit=48")).json();

    const product = data.find((item: any) => item.type === "simple" && item.is_saleable && item.prices?.final);

    expect(product, "Need one saleable simple product").toBeTruthy();

    await addToCartByApi(page, product.id);

    await page.goto("checkout/cart");

    await expect(quantityShown(page)).toHaveText("1");
}

const increase = (page: Page) => page.getByRole("button", { name: "Increase Quantity" }).filter({ visible: true }).first();

/**
 * The quantity between − and +; it has no role of its own.
 */
const quantityShown = (page: Page) => increase(page).locator("xpath=preceding-sibling::p[1]");

const grandTotal = (page: Page) => page.getByText("Grand Total", { exact: true }).locator("xpath=following-sibling::p[1]");

/**
 * The cart totals' box; aria-busy says whether they are being brought up to date.
 */
const totals = (page: Page) => page.locator("[aria-busy]").filter({ has: page.getByText("Grand Total", { exact: true }) });

async function cartQuantity(page: Page): Promise<number> {
    const { data } = await (await page.request.get("api/checkout/cart")).json();

    return Number(data?.items?.[0]?.quantity ?? 0);
}

test("there is no Update Cart button", async ({ page }) => {
    await openCartWithOneItem(page);

    await expect(page.getByRole("button", { name: "Update Cart" })).toHaveCount(0);
});

test("quick − and + clicks save once, half a second after the last, and bring the totals", async ({ page }) => {
    await openCartWithOneItem(page);

    const before = await grandTotal(page).innerText();

    const saves = await holdCartRequests(page, "PUT", 800);

    await increase(page).click();
    await increase(page).click();

    const lastClick = Date.now();

    // The new quantity shows at once, and the totals say they are out of date.
    await expect(quantityShown(page)).toHaveText("3");
    await expect(totals(page)).toHaveAttribute("aria-busy", "true");

    await expect.poll(() => saves[0]?.end).toBeDefined();

    expect(saves).toHaveLength(1);
    expect(saves[0].start - lastClick).toBeGreaterThanOrEqual(400);

    await expect(totals(page)).toHaveAttribute("aria-busy", "false");
    await expect(grandTotal(page)).not.toHaveText(before);
    await expect(quantityShown(page)).toHaveText("3");
    await expect.poll(() => headerCartCount(page)).toBe("3");

    expect(await cartQuantity(page)).toBe(3);
});

test("a quantity the stock cannot cover goes back, with the cart's reason", async ({ page }) => {
    await openCartWithOneItem(page);

    // Stand in for a product with one unit left: answer the save exactly as Cart::updateItems() does when
    // the stock runs out (CardCartTest covers that server rule).
    await holdCartRequests(page, "PUT", 300, (route) => route.fulfill({ json: { data: { message: REFUSED } } }));

    await increase(page).click();

    await expect(quantityShown(page)).toHaveText("2");

    await expect(page.getByText(REFUSED).filter({ visible: true }).first()).toBeVisible();
    await expect(quantityShown(page)).toHaveText("1");
    await expect(totals(page)).toHaveAttribute("aria-busy", "false");

    expect(await cartQuantity(page)).toBe(1);
});

test("Remove still asks first", async ({ page }) => {
    await openCartWithOneItem(page);

    await page.getByRole("button", { name: "Remove", exact: true }).filter({ visible: true }).first().click();

    await expect(page.getByRole("button", { name: "Agree", exact: true })).toBeVisible();

    await page.getByRole("button", { name: "Disagree", exact: true }).click();

    await expect(quantityShown(page)).toHaveText("1");
    expect(await cartQuantity(page)).toBe(1);
});

test("Proceed To Checkout waits for a change that is still to be saved", async ({ page }) => {
    await openCartWithOneItem(page);

    const saves = await holdCartRequests(page, "PUT", 800);

    await increase(page).click();

    await page.getByRole("link", { name: "Proceed To Checkout" }).click();

    await page.waitForURL((url) => ! url.pathname.endsWith("checkout/cart"));

    expect(saves).toHaveLength(1);
    expect(saves[0].end).toBeDefined();
    expect(await cartQuantity(page)).toBe(2);
});
