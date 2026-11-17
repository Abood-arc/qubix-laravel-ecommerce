import { devices, type Page } from "@playwright/test";
import { test, expect } from "../../setup";
import {
    addToCartButton,
    findMultiPhotoProduct,
    openSearchFor,
    setCardStyle,
    styledCard,
    withAdmin,
    type CardProduct,
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

interface Seen {
    flights: number;
    hidden: string;
    pointerEvents: string;
    alt: string;
    bump: boolean;
    bumpVisible: boolean;
    flash: boolean;
}

/**
 * Watch the page from now on: the flying photos added to it (how many, and how the first one looked the
 * moment it appeared), and the animation classes the header cart icon gets (the classes go off again
 * when the animation ends, so a poll could miss them). With `cancel`, each photo's animations are
 * cancelled as it appears, which is what a page that unmounts mid-flight does to them.
 */
const watch = (page: Page, cancel = false) =>
    page.evaluate((cancelFlights) => {
        const seen: Seen = { flights: 0, hidden: "", pointerEvents: "", alt: "", bump: false, bumpVisible: false, flash: false };

        (window as any).cardSeen = seen;

        new MutationObserver((records) =>
            records.forEach((record) => {
                if (record.type === "attributes") {
                    const icon = record.target as HTMLElement;

                    if (icon.matches("[data-cart-target]")) {
                        if (icon.classList.contains("pc-cart-bump")) {
                            seen.bump = true;
                            seen.bumpVisible = icon.getBoundingClientRect().width > 0;
                        }

                        seen.flash ||= icon.classList.contains("pc-cart-flash");
                    }

                    return;
                }

                record.addedNodes.forEach((node) => {
                    if (! (node instanceof HTMLElement) || ! node.classList.contains("pc-fly")) {
                        return;
                    }

                    if (seen.flights++ === 0) {
                        seen.hidden = node.getAttribute("aria-hidden") ?? "";
                        seen.pointerEvents = getComputedStyle(node).pointerEvents;
                        seen.alt = node.querySelector("img")?.getAttribute("alt") ?? "missing";
                    }

                    if (cancelFlights) {
                        node.getAnimations({ subtree: true }).forEach((animation) => animation.cancel());
                    }
                });
            })
        ).observe(document.body, { childList: true, attributes: true, attributeFilter: ["class"], subtree: true });
    }, cancel);

const seen = (page: Page) => page.evaluate(() => (window as any).cardSeen as Seen);

/**
 * Errors the page throws (a failing flight must never be one).
 */
const pageErrors = (page: Page): string[] => {
    const errors: string[] = [];

    page.on("pageerror", (error) => errors.push(error.message));

    return errors;
};

for (const style of ["Swipe, named and sized", "Photo deck"] as CardStyle[]) {
    test.describe(style, () => {
        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, style));
        });

        let product: CardProduct;

        /**
         * Open the search, get ready, and add the product; `before` runs once the page is ready for it.
         */
        async function add(page: Page, options: { cancel?: boolean; before?: () => Promise<void> } = {}) {
            product ??= await findMultiPhotoProduct(page.request);

            await openSearchFor(page, product);
            await watch(page, options.cancel);
            await options.before?.();
            await addToCartButton(page, product, style).click();

            // The card's own − 1 + control: the add went through.
            await expect(styledCard(page, product).getByRole("group", { name: "1 in cart" })).toBeVisible();
        }

        const announcement = (page: Page) => styledCard(page, product).getByRole("status");

        test("a small photo flies to the header cart, which bumps and then settles", async ({ page }) => {
            await add(page);

            await expect.poll(async () => (await seen(page)).bump).toBe(true);
            await expect(page.locator(".pc-fly")).toHaveCount(0);

            // The bump class goes off again with the animation, so it cannot replay later.
            await expect(page.locator("[data-cart-target].pc-cart-bump")).toHaveCount(0);

            // Decoration only: out of the accessibility tree, and never in the way of a click.
            expect(await seen(page)).toMatchObject({ flights: 1, hidden: "true", pointerEvents: "none", alt: "" });
        });

        test("a cancelled flight still leaves nothing behind, and does not bump", async ({ page }) => {
            await add(page, { cancel: true });

            await expect.poll(async () => (await seen(page)).flights).toBe(1);
            await expect(page.locator(".pc-fly")).toHaveCount(0);
            expect((await seen(page)).bump).toBe(false);
        });

        test("a flight that fails cannot get in the way of the add", async ({ page }) => {
            const errors = pageErrors(page);

            await add(page, {
                before: () =>
                    page.evaluate(() => {
                        const animate = Element.prototype.animate;

                        Element.prototype.animate = function (this: Element, ...args: Parameters<Element["animate"]>) {
                            if (this.classList.contains("pc-fly")) {
                                throw new Error("the flight fails");
                            }

                            return animate.apply(this, args);
                        };
                    }),
            });

            // The photo was made, failed, and was taken away again; the card carried on as usual.
            expect((await seen(page)).flights).toBe(1);
            await expect(page.locator(".pc-fly")).toHaveCount(0);
            await expect(announcement(page)).toHaveText("Added");
            await expect(styledCard(page, product).getByRole("group", { name: "1 in cart" })).toBeVisible();
            expect(errors).toEqual([]);
        });

        test("with no header cart showing, nothing flies and the add still works", async ({ page }) => {
            const errors = pageErrors(page);

            await add(page, {
                before: async () => {
                    await page.addStyleTag({ content: "[data-cart-target] { display: none !important; }" });
                },
            });

            await expect(announcement(page)).toHaveText("Added");
            expect(await seen(page)).toMatchObject({ flights: 0, bump: false, flash: false });
            expect(errors).toEqual([]);
        });

        test("with the header cart outside the screen, nothing flies; the count fades in", async ({ page }) => {
            await add(page, {
                before: async () => {
                    await page.addStyleTag({ content: "[data-cart-target] { position: fixed !important; left: -400px; }" });
                },
            });

            await expect.poll(async () => (await seen(page)).flash).toBe(true);
            expect(await seen(page)).toMatchObject({ flights: 0, bump: false });
        });

        test.describe("with reduced motion", () => {
            test.use({ reducedMotion: "reduce" });

            test("nothing flies; the cart count fades in, then the class goes off again", async ({ page }) => {
                await add(page);

                await expect.poll(async () => (await seen(page)).flash).toBe(true);
                await expect(page.locator("[data-cart-target].pc-cart-flash")).toHaveCount(0);
                expect(await seen(page)).toMatchObject({ flights: 0, bump: false });
            });
        });

        test.describe("on a phone", () => {
            test.use(pixel7);

            test("it flies to the header cart that is showing there", async ({ page }) => {
                await add(page);

                // The desktop header's cart is hidden on a phone; the bump lands on the one that shows.
                await expect.poll(async () => (await seen(page)).bump).toBe(true);
                expect(await seen(page)).toMatchObject({ flights: 1, bumpVisible: true });
            });
        });
    });
}
