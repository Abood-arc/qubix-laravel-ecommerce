import { test, expect } from "@playwright/test";

/**
 * An item added while the page's own requests are still loading stays in the cart.
 *
 * Every request saves the session when it ends. Before the fix, a request that started before the add
 * could end after it and save its older copy, without the guest's new cart. It happened about one time in
 * five, so the add is repeated.
 */

const TRIES = 10;

test("an add made while the page is still loading stays in the cart", async ({ browser }) => {
    const setup = await browser.newPage();

    const { data } = await (await setup.request.get("api/products?limit=48")).json();

    const product = data.find((item: any) => item.type === "simple" && item.is_saleable && item.prices?.final);

    expect(product, "Need one saleable simple product").toBeTruthy();

    await setup.close();

    const lost: number[] = [];

    for (let attempt = 1; attempt <= TRIES; attempt++) {
        const context = await browser.newContext();

        const page = await context.newPage();

        // Only until the HTML is in: the page's own requests (products, categories, the cart) are still running.
        await page.goto("", { waitUntil: "domcontentloaded" });

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

        await page.waitForLoadState("networkidle");

        const { data: cart } = await (await page.request.get("api/checkout/cart")).json();

        if (! cart?.items?.some((item: any) => item.product_id === product.id)) {
            lost.push(attempt);
        }

        await context.close();
    }

    expect(lost, `The cart was lost on tries ${lost.join(", ")} of ${TRIES}`).toEqual([]);
});
