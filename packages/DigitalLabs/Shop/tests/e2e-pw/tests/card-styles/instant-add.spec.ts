import { devices, type Locator, type Page } from "@playwright/test";
import { test, expect } from "../../setup";
import {
    addToCartButton,
    cartQuantityOf,
    findSaleableProducts,
    openSearchFor,
    setCardStyle,
    styledCard,
    withAdmin,
    type CardProduct,
    type CardStyle,
} from "../../utils/card-styles";
import { addToCartByApi, blockChatWidget, headerCartCount, holdCartRequests } from "../../utils/cart";

/*
 * Add to cart answers the tap at once, on every card style, on a phone: the card shows the add (Classic:
 * the message; Swipe and Photo deck: the − 1 + control and the flying photo) and the header counts it
 * before the cart has answered; a refusal takes it back and says why. Writes for different products do
 * not wait for each other once there is a cart, and the page's own cart read holds nothing up. Every test
 * starts as a new guest, so with no cart of its own. The cart API is slowed down (page.route) to show the
 * order of things.
 */

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

test.use(pixel7);

// Cart requests held back on purpose can still be on their way when a test ends: let them go quietly.
test.afterEach(async ({ page }) => {
    await page.unrouteAll({ behavior: "ignoreErrors" });
});

test.describe.configure({ mode: "serial" });

test.afterAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
});

const ADDED = "Item Added Successfully";

const REFUSAL = "Test: this bag has just sold out.";

let products: CardProduct[];

test.beforeEach(async ({ page, request }) => {
    products ??= await findSaleableProducts(request, 4);

    await blockChatWidget(page);
});

/**
 * Open a search page that lists exactly `shown`, in that order (the listing API answer is cut down to them).
 */
async function openListing(page: Page, shown: CardProduct[]): Promise<void> {
    const listing = await (await page.request.get("api/products?limit=48")).json();

    const ids = shown.map((product) => product.id);

    const data = ids.map((id) => listing.data.find((item: any) => item.id === id));

    await page.route(/\/api\/products(\?|$)/, async (route) => {
        const response = await route.fetch();

        await route.fulfill({ response, json: { ...(await response.json()), data } });
    });

    await openSearchFor(page, shown[0]);
}

/**
 * The card's Add to cart. The Classic card has no role of its own, so its phone button is found by place
 * (it says "Added" for a moment after a tap).
 */
function addButton(page: Page, style: CardStyle, product: CardProduct, shown: CardProduct[]): Locator {
    if (style === "Classic") {
        return page.getByRole("button", { name: /^(Add To Cart|Added)$/ }).nth(shown.indexOf(product));
    }

    return addToCartButton(page, product, style);
}

/**
 * What the card shows once the product is added: the Classic card's message, or the − 1 + control.
 */
function addedFeedback(page: Page, style: CardStyle, product: CardProduct): Locator {
    if (style === "Classic") {
        return page.getByText(ADDED).filter({ visible: true }).first();
    }

    return styledCard(page, product).getByRole("group", { name: "1 in cart" });
}

for (const style of ["Classic", "Swipe, named and sized", "Photo deck"] as CardStyle[]) {
    test.describe(style, () => {
        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, style));
        });

        test("the add shows at once, and the cart's answer confirms it", async ({ page }) => {
            const [product] = products;

            const adds = await holdCartRequests(page, "POST", 1500);

            await openListing(page, [product]);

            const tapped = Date.now();

            await addButton(page, style, product, [product]).click();

            await expect(addedFeedback(page, style, product)).toBeVisible();
            await expect.poll(() => headerCartCount(page)).toBe("1");

            const shownAfter = Date.now() - tapped;

            test.info().annotations.push({ type: "tap to feedback (ms)", description: String(shownAfter) });

            // All of that before the cart has answered.
            expect(adds).toHaveLength(1);
            expect(adds[0].end).toBeUndefined();

            await expect.poll(() => adds[0].end).toBeDefined();
            await expect.poll(() => cartQuantityOf(page, product)).toBe(1);
            await expect.poll(() => headerCartCount(page)).toBe("1");

            if (style !== "Classic") {
                await expect(addedFeedback(page, style, product)).toBeVisible();
            }
        });

        test("a refused add goes back and shows the cart's reason", async ({ page }) => {
            const [product] = products;

            await holdCartRequests(page, "POST", 800, (route) =>
                route.fulfill({ status: 400, json: { message: REFUSAL } })
            );

            await openListing(page, [product]);

            await addButton(page, style, product, [product]).click();

            await expect.poll(() => headerCartCount(page)).toBe("1");

            await expect(page.getByText(REFUSAL).filter({ visible: true }).first()).toBeVisible();
            await expect.poll(() => headerCartCount(page)).toBe("");
            await expect(addButton(page, style, product, [product])).toBeVisible();

            if (style !== "Classic") {
                await expect(styledCard(page, product).getByRole("group", { name: "1 in cart" })).toBeHidden();
                // Screen readers hear why, not only "Added".
                await expect(styledCard(page, product).getByRole("status")).toHaveText(REFUSAL);
            }

            expect(await cartQuantityOf(page, product)).toBe(0);
        });

        test("with a cart, adds of different products go out side by side", async ({ page }) => {
            const [inCart, first, second] = products;

            await page.goto("");
            await addToCartByApi(page, inCart.id);

            const adds = await holdCartRequests(page, "POST", 1000);

            const shown = [first, second];

            await openListing(page, shown);

            // The page has read the cart, so it knows there is one.
            await expect.poll(() => headerCartCount(page)).toBe("1");

            await addButton(page, style, first, shown).click();
            await addButton(page, style, second, shown).click();

            await expect.poll(() => adds.length).toBe(2);

            // The second went out while the first was still on its way.
            expect(adds[1].start).toBeLessThan(adds[0].end ?? Infinity);

            await expect.poll(() => cartQuantityOf(page, first)).toBe(1);
            await expect.poll(() => cartQuantityOf(page, second)).toBe(1);
            await expect.poll(() => headerCartCount(page)).toBe("3");
        });

        test("with no cart yet, the first adds go one at a time, and both reach the one cart", async ({ page }) => {
            const [, first, second] = products;

            const adds = await holdCartRequests(page, "POST", 800);

            const shown = [first, second];

            await openListing(page, shown);

            await addButton(page, style, first, shown).click();
            await addButton(page, style, second, shown).click();

            // Both show as added at once, and the header counts both.
            await expect.poll(() => headerCartCount(page)).toBe("2");

            await expect.poll(() => adds.length).toBe(2);
            await expect.poll(() => adds[1].end).toBeDefined();

            // The second add waited for the first one's answer: the server makes the cart on the first add.
            expect(adds[1].start).toBeGreaterThanOrEqual(adds[0].end!);

            expect(await cartQuantityOf(page, first)).toBe(1);
            expect(await cartQuantityOf(page, second)).toBe(1);
        });

        test("quick adds show one message, not a pile of them", async ({ page }) => {
            const shown = products.slice(1, 4);

            await page.goto("");
            await addToCartByApi(page, products[0].id);

            await holdCartRequests(page, "POST", 600);

            await openListing(page, shown);

            await expect.poll(() => headerCartCount(page)).toBe("1");

            for (const product of shown) {
                await addButton(page, style, product, shown).click();
            }

            await expect.poll(() => headerCartCount(page)).toBe("4");
            await expect(page.getByText(ADDED).filter({ visible: true })).toHaveCount(1);
        });

        test("a link tapped while the add is on its way goes there once the add is done", async ({ page }) => {
            const [product] = products;

            const adds = await holdCartRequests(page, "POST", 1500);

            await openListing(page, [product]);

            const listing = page.url();

            await addButton(page, style, product, [product]).click();

            await page.getByRole("link", { name: product.name }).first().click();

            // Still here while the add is out: leaving now would drop it, or lose the guest's new cart.
            await page.waitForTimeout(500);
            expect(page.url()).toBe(listing);
            expect(adds[0].end).toBeUndefined();

            await page.waitForURL((url) => url.href !== listing);

            expect(adds[0].end).toBeDefined();
            expect(await cartQuantityOf(page, product)).toBe(1);
        });
    });
}

test.describe("Classic: what happens at the tap", () => {
    test.beforeAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
    });

    test("a double tap adds one, not two", async ({ page }) => {
        const [product] = products;

        const adds = await holdCartRequests(page, "POST", 800);

        await openListing(page, [product]);

        await addButton(page, "Classic", product, [product]).dblclick();

        await expect.poll(() => adds[0]?.end).toBeDefined();
        await page.waitForTimeout(500);

        expect(adds).toHaveLength(1);
        expect(await cartQuantityOf(page, product)).toBe(1);
    });

    test("the button says Added with a tick and the header cart bumps, then it is Add To Cart again", async ({ page }) => {
        const [product] = products;

        const adds = await holdCartRequests(page, "POST", 1500);

        await openListing(page, [product]);

        await countCartBumps(page);

        const button = addButton(page, "Classic", product, [product]);

        await button.click();

        // At the tap, before the cart has answered.
        await expect(button).toHaveAccessibleName("Added");
        await expect(button.locator(".cart-added-tick")).toBeVisible();
        await expect.poll(() => page.evaluate(() => (window as any).cartBumps)).toBe(1);
        expect(adds[0].end).toBeUndefined();

        await expect(button).toHaveAccessibleName("Add To Cart", { timeout: 3000 });
        expect(await cartQuantityOf(page, product)).toBe(1);
    });

    test("a refused add takes Added back as soon as the cart says no", async ({ page }) => {
        const [product] = products;

        await holdCartRequests(page, "POST", 600, (route) =>
            route.fulfill({ status: 400, json: { message: REFUSAL } })
        );

        await openListing(page, [product]);

        const button = addButton(page, "Classic", product, [product]);

        await button.click();

        await expect(button).toHaveAccessibleName("Added");
        await expect(page.getByText(REFUSAL).filter({ visible: true }).first()).toBeVisible();

        // Well before "Added" would have ended on its own (1.5 s after the tap).
        await expect(button).toHaveAccessibleName("Add To Cart", { timeout: 300 });
    });
});

/**
 * Count the header cart icon's bumps (its pc-cart-bump animation starting) into window.cartBumps.
 */
async function countCartBumps(page: Page): Promise<void> {
    await page.evaluate(() => {
        (window as any).cartBumps = 0;

        document.addEventListener("animationstart", (event) => {
            if (event.animationName === "pc-cart-bump") {
                (window as any).cartBumps++;
            }
        });
    });
}

for (const style of ["Swipe, named and sized", "Photo deck"] as CardStyle[]) {
    test.describe(`${style}: what happens at the tap`, () => {
        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, style));
        });

        test("the photo flies to the header cart as the button is tapped", async ({ page }) => {
            const [product] = products;

            const adds = await holdCartRequests(page, "POST", 1500);

            await openListing(page, [product]);

            await page.evaluate(() => {
                (window as any).flights = 0;

                new MutationObserver((records) =>
                    records.forEach((record) =>
                        record.addedNodes.forEach((node) => {
                            if (node instanceof HTMLElement && node.classList.contains("pc-fly")) {
                                (window as any).flights++;
                            }
                        })
                    )
                ).observe(document.body, { childList: true });
            });

            await addButton(page, style, product, [product]).click();

            await expect.poll(() => page.evaluate(() => (window as any).flights)).toBe(1);
            expect(adds[0].end).toBeUndefined();
        });

        test("a tap does not wait for the page's own cart read", async ({ page }) => {
            const [product] = products;

            // Every cart read on the page (the cards' and the header's) takes 3 seconds.
            const reads = await holdCartRequests(page, "GET", 3000);

            const adds = await holdCartRequests(page, "POST", 0);

            await openListing(page, [product]);

            await addButton(page, style, product, [product]).click();

            await expect(addedFeedback(page, style, product)).toBeVisible();
            await expect.poll(() => adds[0]?.end).toBeDefined();

            // The add went out and came back while the reads were still held.
            expect(reads.length).toBeGreaterThan(0);
            expect(adds[0].end!).toBeLessThan(Math.min(...reads.map((read) => read.end ?? Infinity)));
        });

        test("Add, − and Add again before the cart answers leaves one in the cart", async ({ page }) => {
            const [product] = products;

            await holdCartRequests(page, "POST", 800);

            await openListing(page, [product]);

            const card = styledCard(page, product);

            await addButton(page, style, product, [product]).click();
            await card.getByRole("button", { name: `Remove one ${product.name} from cart` }).click();
            await addButton(page, style, product, [product]).click();

            await expect(card.getByRole("group", { name: "1 in cart" })).toBeVisible();
            await expect.poll(() => cartQuantityOf(page, product)).toBe(1);

            // And it stays that way once every write has settled.
            await page.waitForTimeout(1500);
            expect(await cartQuantityOf(page, product)).toBe(1);
            await expect(card.getByRole("group", { name: "1 in cart" })).toBeVisible();
        });

        test("− and + work while the add is still on its way", async ({ page }) => {
            const [product] = products;

            await holdCartRequests(page, "POST", 1200);

            await openListing(page, [product]);

            await addButton(page, style, product, [product]).click();

            await styledCard(page, product).getByRole("button", { name: `Add one more ${product.name}` }).click();

            await expect(styledCard(page, product).getByRole("group", { name: "2 in cart" })).toBeVisible();
            await expect.poll(() => cartQuantityOf(page, product)).toBe(2);
            await expect(styledCard(page, product).getByRole("group", { name: "2 in cart" })).toBeVisible();
        });
    });
}
