import { devices, type Page } from "@playwright/test";
import { test, expect } from "../../setup";
import {
    addToCartButton,
    findMultiPhotoProduct,
    openSearchFor,
    setCardStyle,
    styledCard,
    withAdmin,
    type CardStyle,
} from "../../utils/card-styles";

/*
 * The photo that flies to the header cart after Add to cart on the Swipe and Photo deck cards. The
 * flying photo (.pc-fly) and the cart icon ([data-cart-target], with its pc-cart-* classes) have no
 * role, so CSS locators are the only handle on them.
 */

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

test.describe.configure({ mode: "serial" });

test.afterAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
});

/**
 * Watch the flying photos added to the page from now on (they are removed again when they land): how
 * many there were, and how the first one looked the moment it appeared. With `cancel`, each photo's
 * animations are cancelled as it appears, which is what a page that unmounts mid-flight does to them.
 */
const watchFlights = (page: Page, cancel = false) =>
    page.evaluate((cancelFlights) => {
        const seen = { count: 0, hidden: "", pointerEvents: "", alt: "" };

        (window as any).cardFlights = seen;

        new MutationObserver((records) =>
            records.forEach((record) =>
                record.addedNodes.forEach((node) => {
                    if (! (node instanceof HTMLElement) || ! node.classList.contains("pc-fly")) {
                        return;
                    }

                    if (seen.count++ === 0) {
                        seen.hidden = node.getAttribute("aria-hidden") ?? "";
                        seen.pointerEvents = getComputedStyle(node).pointerEvents;
                        seen.alt = node.querySelector("img")?.getAttribute("alt") ?? "missing";
                    }

                    if (cancelFlights) {
                        node.getAnimations({ subtree: true }).forEach((animation) => animation.cancel());
                    }
                })
            )
        ).observe(document.body, { childList: true });
    }, cancel);

const flights = (page: Page) =>
    page.evaluate(() => (window as any).cardFlights as { count: number; hidden: string; pointerEvents: string; alt: string });

for (const style of ["Swipe, named and sized", "Photo deck"] as CardStyle[]) {
    test.describe(style, () => {
        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, style));
        });

        async function add(page: Page, cancel = false) {
            const product = await findMultiPhotoProduct(page.request);

            await openSearchFor(page, product);
            await watchFlights(page, cancel);
            await addToCartButton(page, product, style).click();

            // The card's own − 1 + control: the add went through.
            await expect(styledCard(page, product).getByRole("group", { name: "1 in cart" })).toBeVisible();
        }

        test("a small photo flies to the header cart, which bumps", async ({ page }) => {
            await add(page);

            await expect.poll(async () => (await flights(page)).count).toBe(1);
            await expect(page.locator("[data-cart-target].pc-cart-bump")).toHaveCount(1);
            await expect(page.locator(".pc-fly")).toHaveCount(0);

            // Decoration only: out of the accessibility tree, and never in the way of a click.
            expect(await flights(page)).toMatchObject({ hidden: "true", pointerEvents: "none", alt: "" });
        });

        test("a cancelled flight still leaves nothing behind, and does not bump", async ({ page }) => {
            await add(page, true);

            await expect.poll(async () => (await flights(page)).count).toBe(1);
            await expect(page.locator(".pc-fly")).toHaveCount(0);
            await expect(page.locator("[data-cart-target].pc-cart-bump")).toHaveCount(0);
        });

        test.describe("with reduced motion", () => {
            test.use({ reducedMotion: "reduce" });

            test("nothing flies; the cart count fades in", async ({ page }) => {
                await add(page);

                await expect(page.locator("[data-cart-target].pc-cart-flash")).toHaveCount(1);
                expect((await flights(page)).count).toBe(0);
            });
        });

        test.describe("on a phone", () => {
            test.use(pixel7);

            test("it flies to the header cart that is showing there", async ({ page }) => {
                await add(page);

                await expect.poll(async () => (await flights(page)).count).toBe(1);

                // The desktop header's cart is hidden on a phone; the bump lands on the one that shows.
                const bumped = page.locator("[data-cart-target].pc-cart-bump");

                await expect(bumped).toHaveCount(1);
                await expect(bumped).toBeVisible();
            });
        });
    });
}
