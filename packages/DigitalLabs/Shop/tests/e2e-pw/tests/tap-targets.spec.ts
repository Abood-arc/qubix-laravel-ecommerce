import { expect, test, type Locator, type Page } from "@playwright/test";
import { addToCartByApi, blockChatWidget } from "../utils/cart";

/**
 * On a 390px phone, the small controls take a finger anywhere in 44 × 44 px around their centre (WCAG
 * 2.5.5), though they look as before (the tap-target utility, tailwind.config.js). Also: the Filter/Sort
 * bar keeps its end clear of the chat button.
 */

test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 3 });

/**
 * Points 21px above, below, before and after the control's centre that a tap there would miss it.
 */
async function missedPoints(target: Locator): Promise<string[]> {
    await target.scrollIntoViewIfNeeded();

    return target.evaluate((element) => {
        const box = element.getBoundingClientRect();

        const x = box.left + box.width / 2;
        const y = box.top + box.height / 2;

        return [[0, -21], [0, 21], [-21, 0], [21, 0]]
            .filter(([dx, dy]) => {
                const hit = document.elementFromPoint(x + dx, y + dy);

                return ! (hit && element.contains(hit));
            })
            .map(([dx, dy]) => `${dx},${dy}`);
    });
}

async function expectFingerSized(targets: Record<string, Locator>): Promise<void> {
    for (const [name, target] of Object.entries(targets)) {
        await expect(target, name).toBeVisible();

        expect(await missedPoints(target), `${name}: points within 22px of its centre that miss it`).toEqual([]);
    }
}

async function aCartWithOneItem(page: Page): Promise<{ urlKey: string }> {
    await blockChatWidget(page);

    await page.goto("");

    const { data } = await (await page.request.get("api/products?limit=48")).json();

    const product = data.find((item: any) => item.type === "simple" && item.is_saleable && item.prices?.final);

    expect(product, "Need one saleable simple product").toBeTruthy();

    await addToCartByApi(page, product.id);

    return { urlKey: product.url_key };
}

const visible = (locator: Locator) => locator.filter({ visible: true }).first();

test("the quantity − and + on the product page", async ({ page }) => {
    const { urlKey } = await aCartWithOneItem(page);

    await page.goto(urlKey);

    await expectFingerSized({
        "−": visible(page.getByRole("button", { name: "Decrease Quantity" })),
        "+": visible(page.getByRole("button", { name: "Increase Quantity" })),
    });
});

test("the cart page: − and +, Remove and Apply Coupon", async ({ page }) => {
    await aCartWithOneItem(page);

    await page.goto("checkout/cart");

    await expectFingerSized({
        "−": visible(page.getByRole("button", { name: "Decrease Quantity" })),
        "+": visible(page.getByRole("button", { name: "Increase Quantity" })),
        Remove: visible(page.getByRole("button", { name: "Remove", exact: true })),
        "Apply Coupon": visible(page.getByRole("button", { name: "Apply Coupon" })),
    });
});

test("the cart drawer: − and +, Remove and View Cart", async ({ page }) => {
    await aCartWithOneItem(page);

    await page.goto("");

    await visible(page.getByRole("button", { name: "Shopping Cart" })).click();

    await expectFingerSized({
        "−": visible(page.getByRole("button", { name: "Decrease Quantity" })),
        "+": visible(page.getByRole("button", { name: "Increase Quantity" })),
        Remove: visible(page.getByRole("button", { name: "Remove", exact: true })),
        "View Cart": visible(page.getByRole("link", { name: "View Cart" })),
    });
});

test("the sign-in and register links", async ({ page }) => {
    await blockChatWidget(page);

    await page.goto("customer/login");

    await expectFingerSized({
        "Forgot Password?": page.getByRole("link", { name: "Forgot Password?" }),
        "Create your account": page.getByRole("link", { name: "Create your account" }),
    });

    await page.goto("customer/register");

    await expectFingerSized({
        "Sign In": page.getByRole("link", { name: "Sign In", exact: true }).last(),
    });
});

/*
 * The chat button sits bottom right, 56px wide and 20px in from the edge, in Arabic too (measured on the
 * live widget, 2026-11-19). The bar's buttons end before it, as the product page's buy bar does.
 */
for (const locale of ["en", "ar"]) {
    test(`${locale}: the Filter/Sort bar keeps clear of the chat button`, async ({ page }) => {
        await blockChatWidget(page);

        await page.goto(`search?query=a&locale=${locale}`);

        // The bar has no role of its own; its two toggles are buttons.
        const toggles = page.locator(".category-mobile-bar").getByRole("button");

        await expect(toggles).toHaveCount(2);

        const rightEdges = await toggles.evaluateAll((elements) => elements.map((element) => element.getBoundingClientRect().right));

        expect(Math.max(...rightEdges)).toBeLessThanOrEqual(390 - 76);
    });
}
