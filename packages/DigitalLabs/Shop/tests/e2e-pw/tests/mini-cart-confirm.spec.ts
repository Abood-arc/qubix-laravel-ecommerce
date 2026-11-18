import { test, expect, type Locator, type Page } from "@playwright/test";

/**
 * Removing an item from the cart drawer asks "Are you sure?" first.
 * The question must sit above the open drawer, and answering it must leave the page behind the drawer locked.
 * Only a fresh guest cart is touched.
 */

async function putASaleableProductInTheCart(page: Page): Promise<void> {
    await page.goto("");

    const response = await page.request.get("api/products?limit=48");

    expect(response.ok()).toBeTruthy();

    const { data } = await response.json();

    const product = data.find((item: any) => item.is_saleable && item.prices?.final);

    expect(product, "Need one saleable simple product").toBeTruthy();

    const added = await page.evaluate(async (productId) => {
        const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? "");

        const reply = await fetch("api/checkout/cart", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": token },
            body: JSON.stringify({ product_id: productId, quantity: 1 }),
        });

        return reply.ok;
    }, product.id);

    expect(added).toBeTruthy();

    await page.reload();
}

/** True when a click in the middle of the element would land on it, not on something drawn over it. */
function isOnTop(target: Locator): Promise<boolean> {
    return target.evaluate((element) => {
        const box = element.getBoundingClientRect();

        const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);

        return hit !== null && element.contains(hit);
    });
}

const bodyOverflow = (page: Page) => page.evaluate(() => document.body.style.overflow);

test.describe("Removing an item from the cart drawer", () => {
    test.beforeEach(async ({ page }) => {
        await putASaleableProductInTheCart(page);

        await page.getByRole("button", { name: "Shopping Cart" }).first().click();

        await page.getByRole("button", { name: "Remove" }).first().click();
    });

    test("the question shows above the drawer and can be answered", async ({ page }) => {
        const agree = page.getByRole("button", { name: "Agree", exact: true });

        await expect(agree).toBeVisible();

        expect(await isOnTop(agree)).toBe(true);

        expect(await isOnTop(page.getByRole("button", { name: "Disagree", exact: true }))).toBe(true);

        await agree.click();

        await expect(page.getByRole("button", { name: "Remove" })).toHaveCount(0);
    });

    test("saying no keeps the drawer open and the page behind it still", async ({ page }) => {
        await page.getByRole("button", { name: "Disagree", exact: true }).click();

        await expect(page.getByRole("button", { name: "Remove" }).first()).toBeVisible();

        expect(await bodyOverflow(page)).toBe("hidden");
    });
});
