import { devices, type Page } from "@playwright/test";
import { test, expect } from "../../setup";
import {
    addToCartButton,
    cartQuantityOf,
    findMultiPhotoProduct,
    openSearchFor,
    setCardStyle,
    styledCard,
    withAdmin,
    type CardProduct,
    type CardStyle,
} from "../../utils/card-styles";

/*
 * The − 1 + control that Add to cart becomes on the Swipe and Photo deck cards. Every test starts as a
 * new guest, so with an empty cart of its own.
 */

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

const REFUSED = "The requested quantity is not available, please try again later.";

test.describe.configure({ mode: "serial" });

test.afterAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
});

let product: CardProduct;

test.beforeEach(async ({ request }) => {
    product ??= await findMultiPhotoProduct(request);
});

const stepper = (page: Page, count: number) =>
    styledCard(page, product).getByRole("group", { name: `${count} in cart` });

const addOne = (page: Page) => styledCard(page, product).getByRole("button", { name: `Add one more ${product.name}` });

const removeOne = (page: Page) =>
    styledCard(page, product).getByRole("button", { name: `Remove one ${product.name} from cart` });

async function addFromCard(page: Page, style: CardStyle): Promise<void> {
    await openSearchFor(page, product);
    await addToCartButton(page, product, style).click();
    await expect(stepper(page, 1)).toBeVisible();
}

for (const style of ["Swipe, named and sized", "Photo deck"] as CardStyle[]) {
    test.describe(`${style}: the − 1 + control`, () => {
        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, style));
        });

        test("Add to cart becomes − 1 +, which changes the real cart and removes the line at 0", async ({ page }) => {
            await addFromCard(page, style);

            await addOne(page).click();

            await expect(stepper(page, 2)).toBeVisible();
            await expect(styledCard(page, product).getByRole("status")).toHaveText("2 in cart");
            expect(await cartQuantityOf(page, product)).toBe(2);

            await removeOne(page).click();

            await expect(stepper(page, 1)).toBeVisible();
            await expect.poll(() => cartQuantityOf(page, product)).toBe(1);

            await removeOne(page).click();

            await expect(page.getByText("Item is successfully removed from the cart.").first()).toBeVisible();
            await expect(addToCartButton(page, product, style)).toBeVisible();
            await expect(styledCard(page, product).getByRole("status")).toHaveText("Item is successfully removed from the cart.");
            expect(await cartQuantityOf(page, product)).toBe(0);
        });

        test("quick clicks all count, and the requests never overlap", async ({ page }) => {
            await addFromCard(page, style);

            // Slow every quantity change down, so the clicks land while a request is still out.
            let inFlight = 0;
            let overlapped = false;
            let updates = 0;

            await page.route(/\/api\/checkout\/cart(\?|$)/, async (route) => {
                if (route.request().method() !== "PUT") {
                    return route.continue();
                }

                updates++;
                overlapped ||= inFlight > 0;
                inFlight++;

                await new Promise((resolve) => setTimeout(resolve, 400));

                const response = await route.fetch();

                inFlight--;

                await route.fulfill({ response });
            });

            for (let click = 0; click < 4; click++) {
                await addOne(page).click();
            }

            // The control counts every click straight away.
            await expect(stepper(page, 5)).toBeVisible();

            await expect.poll(() => cartQuantityOf(page, product)).toBe(5);
            await expect(styledCard(page, product).getByRole("status")).toHaveText("5 in cart");

            expect(overlapped).toBe(false);
            expect(updates).toBeLessThan(4);
        });

        test("a quantity the stock cannot cover is refused, as on the cart page", async ({ page }) => {
            await addFromCard(page, style);

            // Stand in for a product with one unit left: answer the update exactly as Cart::updateItems()
            // does when the stock runs out (CardCartTest covers that server rule).
            await page.route(/\/api\/checkout\/cart(\?|$)/, (route) =>
                route.request().method() === "PUT"
                    ? route.fulfill({ json: { data: { message: REFUSED } } })
                    : route.continue()
            );

            await addOne(page).click();

            await expect(page.getByText(REFUSED).first()).toBeVisible();
            await expect(stepper(page, 1)).toBeVisible();
            expect(await cartQuantityOf(page, product)).toBe(1);
        });

        test("keyboard: focus moves to + once the control shows, and back to Add to cart at 0", async ({ page }) => {
            await openSearchFor(page, product);

            await addToCartButton(page, product, style).focus();
            await page.keyboard.press("Enter");

            await expect(addOne(page)).toBeFocused();

            await page.keyboard.press("Shift+Tab");
            await expect(removeOne(page)).toBeFocused();
            await page.keyboard.press("Enter");

            await expect(addToCartButton(page, product, style)).toBeFocused();
            await expect.poll(() => cartQuantityOf(page, product)).toBe(0);
        });

        test("the card follows the cart drawer: a quantity change and a removal", async ({ page }) => {
            await addFromCard(page, style);

            await page.getByRole("button", { name: "Shopping Cart" }).filter({ visible: true }).click();

            await page.getByRole("button", { name: "Increase Quantity" }).filter({ visible: true }).first().click();

            await expect(stepper(page, 2)).toBeAttached();
            await expect.poll(() => cartQuantityOf(page, product)).toBe(2);

            await page.getByRole("button", { name: "Remove", exact: true }).filter({ visible: true }).first().click();

            // The shop's confirm dialog opens under the drawer's backdrop, which takes the mouse (an existing
            // shop issue, not the cards'), so confirm the way a keyboard user does.
            await page.getByRole("button", { name: "Agree", exact: true }).press("Enter");

            await expect(page.getByText("Item is successfully removed from the cart.").first()).toBeVisible();
            await expect(addToCartButton(page, product, style)).toBeAttached();
            await expect(stepper(page, 1)).not.toBeAttached();
        });
    });
}

test.describe("Photo deck on a phone", () => {
    test.use(pixel7);

    test.beforeAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));
    });

    test("the − 1 + control grows across the photo and keeps 44px buttons", async ({ page }) => {
        await openSearchFor(page, product);

        // The hidden control must not stretch the round bag button beside it.
        expect((await addToCartButton(page, product, "Photo deck").boundingBox())!.width).toBeLessThanOrEqual(44);

        await addToCartButton(page, product, "Photo deck").tap();

        const group = stepper(page, 1);

        await expect(group).toBeVisible();
        await expect.poll(async () => (await group.boundingBox())!.width).toBeGreaterThan(3 * 44);

        const card = (await styledCard(page, product).boundingBox())!;
        const plus = (await addOne(page).boundingBox())!;

        expect((await group.boundingBox())!.width).toBeLessThanOrEqual(card.width);
        expect(plus.width).toBeGreaterThanOrEqual(44);
        expect(plus.height).toBeGreaterThanOrEqual(44);
    });
});
