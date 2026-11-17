import { test, expect } from "../../setup";
import {
    findMultiPhotoProduct,
    findSaleableProducts,
    openSearchFor,
    setCardStyle,
    withAdmin,
    type CardStyle,
} from "../../utils/card-styles";

test.describe.configure({ mode: "serial" });

test.afterAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
});

test.describe("Classic card", () => {
    test.beforeAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
    });

    test("grid cards are today's card", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);

        await expect(page.getByText(product.name, { exact: true }).first()).toBeVisible();
        // No styled card on the page: only the new templates carry data-card-style.
        await expect(page.locator("[data-card-style]")).toHaveCount(0);
    });

    test("Add To Cart and Add To Compare behave as before", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);
        await page.evaluate(() => localStorage.removeItem("compare_items"));
        await page.reload();

        await page.getByText(product.name, { exact: true }).first().hover();
        await page.getByRole("button", { name: "Add To Cart" }).first().click();

        await expect(page.getByText("Item Added Successfully").first()).toBeVisible();

        await page.getByText(product.name, { exact: true }).first().hover();
        await page.getByRole("button", { name: "Add To Compare" }).first().click();

        await expect(page.getByText("Item added successfully to compare list.").first()).toBeVisible();
    });
});

for (const style of ["Swipe, named and sized", "Photo deck"] as CardStyle[]) {
    test.describe(`List view with ${style}`, () => {
        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, style));
        });

        test("list cards stay today's list card", async ({ page }) => {
            const product = await findMultiPhotoProduct(page.request);

            await openSearchFor(page, product, "&mode=list");

            await expect(page.getByText(product.name, { exact: true }).first()).toBeVisible();
            await expect(page.locator("[data-card-style]")).toHaveCount(0);
            await expect(page.getByRole("button", { name: "Add To Cart" }).first()).toBeVisible();
        });
    });
}

test.describe("Classic-only pages with a new style chosen", () => {
    test.beforeAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Swipe, named and sized"));
    });

    test("the compare page and the cart page show the Classic card and no tray", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await page.goto("");
        await page.evaluate((id) => localStorage.setItem("compare_items", JSON.stringify([id])), product.id);

        await page.goto("compare");

        await expect(page.getByText(product.name, { exact: true }).first()).toBeVisible();
        await expect(page.locator("[data-card-style]")).toHaveCount(0);
        await expect(page.getByRole("region", { name: "Compare tray" })).toHaveCount(0);

        await page.goto("checkout/cart");

        await expect(page.locator("[data-card-style]")).toHaveCount(0);
        await expect(page.getByRole("region", { name: "Compare tray" })).toHaveCount(0);
    });

    // The dev catalogue has no cross-sell products, so the row would never render. Answer its API with real
    // listing products, to prove the row's cards stay Classic.
    test("the cart's More choices row shows Classic cards", async ({ page }) => {
        const products = await findSaleableProducts(page.request, 2);

        const listing = await (await page.request.get("api/products?limit=48")).json();

        const ids = products.map((product) => product.id);

        await page.route(/\/api\/checkout\/cart\/cross-sell/, (route) =>
            route.fulfill({
                json: { data: listing.data.filter((item: any) => ids.includes(item.id)), links: {}, meta: {} },
            }),
        );

        await page.goto("checkout/cart");

        await expect(page.getByRole("heading", { name: "More choices" })).toBeVisible();
        await expect(page.getByText(products[0].name, { exact: true }).first()).toBeVisible();
        await expect(page.locator("[data-card-style]")).toHaveCount(0);

        // Today's card: a hover reveals Add To Cart and Add To Compare buttons.
        await page.getByText(products[0].name, { exact: true }).first().hover();
        await expect(page.getByRole("button", { name: "Add To Cart" }).first()).toBeVisible();
        await expect(page.getByRole("button", { name: "Add To Compare" }).first()).toBeVisible();
    });
});
